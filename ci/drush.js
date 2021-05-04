// @ts-check

const dedent = require("dedent");

const ecs = require("./ecs");
const ui = require("./ui");
const util = require("./util");

/**
 * The Drush update script to run in ECS.
 *
 * (This is defined here instead of ecs.js since it is the most likely thing to change, so
 * we provide it here at the entry point of the script rather than accidentally hide it in
 * ecs.js.)
 */
const drushScript = dedent`
  drush --debug --uri="$WEBCMS_SITE_URL" sset system.maintenance_mode 1 --input-format=integer
  drush --debug --uri="$WEBCMS_SITE_URL" cr
  drush --debug --uri="$WEBCMS_SITE_URL" updb -y
  drush --debug --uri="$WEBCMS_SITE_URL" cim -y
  drush --debug --uri="$WEBCMS_SITE_URL" sset system.maintenance_mode 0 --input-format=integer
  drush --debug --uri="$WEBCMS_SITE_URL" cr
`;

/**
 * The main function for Drush updates. This function coordinates the three steps needed
 * to carry out a deployment:
 *
 * 1. Stop running Drupal tasks.
 * 2. Spawn Drush
 *
 * @function
 */
async function main() {
  // Wipe the running Drupal tasks since they're most likely stale. See the documentation
  // for `stopRunningTasks` for why.
  ui.logHeading("ecs", "Stopping Drupal tasks");

  const count = await ecs.stopRunningTasks();
  ui.log(`Stopped ${count} tasks.`);
  ui.log();

  ui.logHeading("ecs", "Running Drush");

  const task = await ecs.startDrushTask(drushScript);
  const taskUrl = await util.getTaskUrl(task);

  ui.log(ui.link(taskUrl, `Task ${task.split("/").pop()}`));
  ui.log();

  /**
   * Tracks the last status returned by ECS. We use this to avoid repeatedly printing the
   * same state for each iteration of the polling loop, which can become especially
   * egregious if Drush is performing database updates, as that can take several minutes,
   * which would equate to 12 lines of "Drush status: RUNNING" per minute.
   *
   * @type {string=}
   */
  let lastSeenStatus;

  /**
   * The final status of the Drush task.
   *
   * NB. This value _must_ be assigned in the polling loop below.
   *
   * @type {ecs.Status}
   */
  let finalStatus;

  // Watch the status of the Drush task. It is important to block the CI/CD build until
  // Drush has finished (successfully or otherwise), because once this script exits, the
  // CI platform will allow other pending Drush updates to run.
  //
  // Note that while the AWS SDK does include helpers for waiting on a task to finish,
  // we poll manually in order to output progress information to the console. (Waiters are
  // single-shot and have a maximum timeout.)
  while (true) {
    // We wait at the start of the loop to allow ECS' eventual consistency to "settle",
    // preventing "task not found" errors that may appear if we check the status too
    // early.
    await util.delay();

    const status = await ecs.getDrushStatus(task);

    // If we got a status object, that means Drush finished. We can break from the loop
    // and present the information to the CI console.
    if (typeof status === "object") {
      finalStatus = status;
      break;
    }

    // As mentioned in the comments for `lastStatus`, we only print the Drush status if it
    // changed since the last time we checked.
    if (status !== lastSeenStatus) {
      lastSeenStatus = status;
      ui.log(`Drush status: ${lastSeenStatus}`);
    }
  }

  const info = util.inspectDrushStatus(finalStatus);

  ui.log(`Task stop: ${info.stop}`);
  ui.log(`Drush exit: ${info.exit}`);
  if (info.signal) {
    ui.log(`  NOTE: Drush exited from signal ${info.signal}`);
  }

  if (!info.success) {
    throw new Error("Drush task did not exit cleanly");
  }
}

main()
  .catch((error) => {
    // Output a notification so the log is pre-expanded in Buildkite builds.
    ui.notify();

    console.error(String(error));
    process.exitCode = 1;
  })
  .then(() => {
    process.exit();
  });
