// @ts-check

/**
 * @module
 */

const ECS = require("@aws-sdk/client-ecs");

const ssm = require("./ssm");
const vars = require("./vars");

const client = new ECS.ECSClient({ region: vars.region });

/**
 * Helper function to transform an array of ECS API failure objects into a bulleted string
 * list.
 *
 * @param {ReadonlyArray<ECS.Failure>} failures The ECS failure objects
 * @return {string}
 *
 * @package
 */
function aggregateFailures(failures) {
  return failures
    .map(({ reason, detail }) =>
      detail ? `* ${reason} (${detail})` : `* ${reason}`
    )
    .join("\n");
}

/**
 * Stops running Drupal tasks. This function should be run prior to a deployment in order
 * to avoid a situation where stale Drupal can corrupt the caches. Since Drupal is backed
 * by an ECS service, ECS will start new Drupal tasks. We do not care about the timing of
 * this because by the time we are running Drush, the ECS task definitions have been
 * updated by Terraform, and we can assume that new tasks will therefore be in sync with
 * Drush's own code and configuration.
 *
 * @returns {Promise<number>} The number of tasks that were stopped by this function.
 *
 * @public
 */
async function stopRunningTasks() {
  const cluster = await ssm.getParameter("ecs/cluster-name");
  const family = `webcms-${vars.environment}-${vars.site}-${vars.lang}-drupal`;

  let stoppedCount = 0;

  // Paginate with a small page size as we stop every task in parallel (this avoids rate
  // limiting and some other "too many requests"-related issues).
  const paginator = ECS.paginateListTasks(
    { client },
    { cluster, family, maxResults: 5 }
  );

  for await (const page of paginator) {
    const promises = page.taskArns.map(async (task) => {
      const command = new ECS.StopTaskCommand({
        cluster,
        task,
        reason: `Deployment ${vars.imageTag}`,
      });
      await client.send(command);
    });

    // Wait on every promise at once
    await Promise.all(promises);

    stoppedCount += promises.length;
  }

  return stoppedCount;
}

/**
 * Spawns a new Drush task. The task's "started by" field is associated with the value of
 * `$WEBCMS_IMAGE_TAG` from the environment, since it is a build-specific identifier.
 *
 * Note that this function returns success as soon as ECS acknowledges the task's
 * creation. Callers of this function must arrange to check the status themselves if they
 * want to observe Drush's success/failure. See `getDrushStatus` for a helper function to
 * accomplish this.
 *
 * @param {string} script The Drush script to run.
 * @return {Promise<string>} The ARN of the newly-spawned Drush task.
 *
 * @public
 */
async function startDrushTask(script) {
  const cluster = await ssm.getParameter("ecs/cluster-name");
  const taskDefinition = `webcms-${vars.environment}-${vars.site}-${vars.lang}-drush`;

  const privateSubnets = await ssm.getParameter("vpc/private-subnets");
  const securityGroup = await ssm.getParameter("security-groups/drupal");

  const command = new ECS.RunTaskCommand({
    cluster,
    taskDefinition,

    // Identify this Drush invocation with the build tag, as mentioned above
    startedBy: `build/${vars.imageTag}`,

    // Change the 'command' field to use sh - this is how we support multi-line shell
    // scripts such as the one in drush.js.
    overrides: {
      containerOverrides: [
        { name: "drush", command: ["/bin/sh", "-exc", script] },
      ],
    },

    launchType: 'FARGATE',

    // This network configuration is required by Fargate since it uses AWSVPC networking
    // exclusively.
    networkConfiguration: {
      awsvpcConfiguration: {
        assignPublicIp: "DISABLED",
        subnets: privateSubnets.split(","),
        securityGroups: [securityGroup],
      },
    },
  });

  const response = await client.send(command);
  if (response.failures && response.failures.length > 0) {
    throw new Error(aggregateFailures(response.failures));
  }

  // We always know we only started one task, so we can shortcut to just the ARN.
  return response.tasks[0].taskArn;
}

/**
 * A status object, as returned by `getDrushStatus()`. This is a heavily stripped-down
 * version of the [ECS API's `Task`
 * object](https://docs.aws.amazon.com/AmazonECS/latest/APIReference/API_Task.html),
 * exploiting the knowledge that we are only ever inspecting a single-container Drush
 * task.
 *
 * @typedef {object} Status
 *
 * @prop {string=} stopCode When a task stops, the code is a machine-readable identifier
 * indicating why a task stopped (for example, `EssentialContainerExited`).
 *
 * @prop {string=} stopReason When present, the stop reason is a short elaboration of the
 * reason a task stopped, in case ECS is able to provide additional information.
 *
 * @prop {number=} exitCode The normal *nix exit code. If this is not present (i.e., is
 * `undefined`) then it usually indicates that the task did not even start.
 *
 * @prop {string=} exitReason Usually this is an elaboration of the exit code (for
 * example, a message about the container running out of memory), but if the task failed
 * to start successfully, this can be the error message indicating why this container
 * failed.
 *
 * @public
 */

/**
 * Fetches the status of an ECS task. If the task's status is anything but `STOPPED` (see
 * the [task lifecycle
 * documentation](https://docs.aws.amazon.com/AmazonECS/latest/developerguide/task-lifecycle.html))
 * then this function just returns that status code. Once Drush has stopped, this function
 * collects exit information for both the task and container (when possible) and returns
 * it as a `Status` object.
 *
 * An important note for callers of this function: per the diagram in the link above, we
 * assume that all tasks, regardless of success or failure, will enter the STOPPED state.
 * This implies that this function returning a `Status` object is guaranteed, and can be
 * used as an exit condition in polling loops.
 *
 * @param {string} task The ARN of a spawned Drush task.
 * @return {Promise<string | Status>} The string status name, or an exit status object.
 *
 * @public
 */
async function getDrushStatus(task) {
  const cluster = await ssm.getParameter("ecs/cluster-name");

  const command = new ECS.DescribeTasksCommand({ cluster, tasks: [task] });
  const response = await client.send(command);
  if (response.failures && response.failures.length > 0) {
    throw new Error(aggregateFailures(response.failures));
  }

  const info = response.tasks[0];
  if (info.lastStatus !== "STOPPED") {
    return info.lastStatus;
  }

  const { stopCode, stoppedReason, containers } = info;

  // Carefully read the exit code and reason of the container: the extra nullish operators
  // here guard against situations where the task couldn't be started and thus could not
  // construct container statuses.
  const { exitCode, reason } = containers?.[0] ?? {};

  // Rename the reason fields to better indicate which code they're associated with.
  return { stopCode, stopReason: stoppedReason, exitCode, exitReason: reason };
}

exports.stopRunningTasks = stopRunningTasks;
exports.startDrushTask = startDrushTask;
exports.getDrushStatus = getDrushStatus;
