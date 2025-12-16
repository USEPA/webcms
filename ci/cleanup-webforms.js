// @ts-check
// One-time script to delete all webform submissions and webforms
// This resolves config import conflicts when webforms have submissions

const ecs = require("./ecs");
const ui = require("./ui");
const util = require("./util");

const cleanupScript = `
  echo "Deleting all webform submissions and webforms..."
  drush --uri="$WEBCMS_SITE_URL" sql:query "DELETE FROM webform_submission"
  drush --uri="$WEBCMS_SITE_URL" sql:query "DELETE FROM webform_submission_data"
  drush --uri="$WEBCMS_SITE_URL" sql:query "DELETE FROM config WHERE name LIKE 'webform.webform.%'"
  drush --uri="$WEBCMS_SITE_URL" cache:rebuild
  echo "Cleanup complete!"
`;

async function main() {
  ui.logHeading("ecs", "Running webform cleanup on dev environment");

  const task = await ecs.startDrushTask(cleanupScript);
  const taskUrl = await util.getTaskUrl(task);

  ui.log(ui.link(taskUrl, `Task ${task.split("/").pop()}`));
  ui.log();

  let lastSeenStatus;
  let finalStatus;

  const maxIterations = 120; // 10 minutes at 5-second intervals
  let iterationCount = 0;

  while (iterationCount < maxIterations) {
    await util.delay();
    const status = await ecs.getDrushStatus(task);

    if (typeof status === "object") {
      finalStatus = status;
      break;
    }

    if (status !== lastSeenStatus) {
      lastSeenStatus = status;
      ui.log(`Cleanup status: ${lastSeenStatus}`);
    }

    iterationCount++;
  }

  if (iterationCount >= maxIterations) {
    throw new Error(`Cleanup task did not complete within ${maxIterations * 5 / 60} minutes`);
  }

  const info = util.inspectDrushStatus(finalStatus);

  ui.log(`Task stop: ${info.stop}`);
  ui.log(`Cleanup exit: ${info.exit}`);
  if (info.signal) {
    ui.log(`  NOTE: Cleanup exited from signal ${info.signal}`);
  }

  ui.log();

  const logsUrl = await util.getLogsUrl(task);
  ui.log(ui.link(logsUrl, `Task logs`));

  if (!info.success) {
    throw new Error("Cleanup task did not exit cleanly");
  }

  ui.log();
  ui.log("✅ Webforms and submissions successfully deleted!");
  ui.log("You can now run the deployment pipeline.");
}

main()
  .catch((error) => {
    ui.notify();
    console.error(String(error));
    process.exitCode = 1;
  })
  .then(() => {
    process.exit();
  });
