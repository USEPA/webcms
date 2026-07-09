// @ts-check

const dedent = require("dedent");

const ecs = require("./ecs");
const ui = require("./ui");
const util = require("./util");
const vars = require("./vars");

/**
 * The Drush update script to run in ECS.
 *
 * (This is defined here instead of ecs.js since it is the most likely thing to change, so
 * we provide it here at the entry point of the script rather than accidentally hide it in
 * ecs.js.)
 *
 * HARDENING #1 — bootstrap-free maintenance mode (via `drush sql:query`)
 * HARDENING #2 — no leading `drush cr`
 *   WHY:  During a deploy the site may be mid-migration, or the freshly deployed
 *         image may not yet match the database schema. Any command that needs a
 *         full Drupal *bootstrap* (`drush cr`, `drush state:set`/`sset`) can throw
 *         a fatal in that window — the container exits 255 and, because the task
 *         runs under `sh -exc` (fail-fast), the script aborts. Older revisions
 *         opened with `drush cr` and toggled maintenance mode with `sset`, so they
 *         could die on the FIRST line — before maintenance mode was even set —
 *         stranding the site and failing the deploy.
 *   WHAT: Toggle `system.maintenance_mode` by writing straight to Drupal's
 *         `key_value` table with `drush sql:query`, and do not run a pre-emptive
 *         `drush cr`.
 *   HOW:  `sql:query` needs only a database connection, not a working bootstrap,
 *         so it still works when the app is unhealthy. `'i:1;'`/`'i:0;'` are the
 *         PHP-serialized integers Drupal's State API stores for maintenance mode.
 *         Cache rebuild is handled by `drush deploy` (its final step is
 *         `cache:rebuild`), so a separate leading `cr` is redundant and risky.
 */

const drushScript = dedent`
  drush --debug --uri="$WEBCMS_SITE_URL" sql:query "REPLACE INTO key_value (collection, name, value) VALUES ('state', 'system.maintenance_mode', 'i:1;')"
  drush --debug --uri="$WEBCMS_SITE_URL" deploy -y
  drush --debug --uri="$WEBCMS_SITE_URL" sql:query "REPLACE INTO key_value (collection, name, value) VALUES ('state', 'system.maintenance_mode', 'i:0;')"
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
  // HARDENING #3 — deployed image-tag drift check.
  //   WHY:  If the Drush Update runs without a fresh `deploy:*:apply-*` (e.g.
  //         someone triggers the update before/without the apply), Drush runs
  //         against a STALE image — running new migrations against old code (or
  //         vice versa). That is one of the most common and most confusing causes
  //         of a failed or inconsistent deploy.
  //   WHAT: Before doing anything else, compare the image tag baked into the
  //         currently-deployed ECS Drush task definition against the tag this
  //         pipeline built.
  //   HOW:  `ecs.getDeployedImageTag()` reads the live task definition; we compare
  //         it to `vars.imageTag`. This is ADVISORY ONLY — on mismatch we print a
  //         loud warning telling the operator to re-run the matching apply job, but
  //         we do NOT abort (a transient AWS read error must not fail a good deploy).
  ui.logHeading("ecs", "Verifying deployed image tag");

  const deployedTag = await ecs.getDeployedImageTag();
  if (!deployedTag) {
    ui.log(
      "⚠️  Could not determine the deployed image tag from ECS; skipping drift check."
    );
  } else if (deployedTag === vars.imageTag) {
    ui.log(`Deployed tag matches build tag (${vars.imageTag}).`);
  } else {
    ui.log("⚠️  WARNING: Deployed image tag does not match this build's tag.");
    ui.log(`    Build tag:    ${vars.imageTag}`);
    ui.log(`    Deployed tag: ${deployedTag}`);
    ui.log(
      "    Drush will run against the currently-deployed image. If this is unexpected,"
    );
    ui.log(
      `    re-run the corresponding deploy:${vars.site}:apply-${vars.lang} job first.`
    );
  }
  ui.log();

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
  //
  // HARDENING #5 — bounded polling with a hard timeout.
  //   WHY:  An unbounded `while (true)` poll can hang a pipeline indefinitely if a
  //         Drush task never reaches STOPPED, tying up a runner and hiding failures.
  //   WHAT/HOW: Cap the loop at `maxIterations` (360 × 5s = 30 min) and throw a
  //         clear timeout error if exceeded, so the job fails fast and visibly.
  const maxIterations = 360; // 30 minutes at 5-second intervals
  let iterationCount = 0;

  while (iterationCount < maxIterations) {
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

    iterationCount++;
  }

  // If we exceeded the maximum iterations, throw an error
  if (iterationCount >= maxIterations) {
    throw new Error(`Drush task did not complete within ${maxIterations * 5 / 60} minutes`);
  }

  const info = util.inspectDrushStatus(finalStatus);

  ui.log(`Task stop: ${info.stop}`);
  ui.log(`Drush exit: ${info.exit}`);
  if (info.signal) {
    ui.log(`  NOTE: Drush exited from signal ${info.signal}`);
  }

  // Output a blank line before outputting the logs link
  ui.log();

  const logsUrl =  await util.getLogsUrl(task);
  ui.log(ui.link(logsUrl, `Task logs`));

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
