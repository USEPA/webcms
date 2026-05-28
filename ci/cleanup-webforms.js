// @ts-check
// One-time script to delete all webform submissions and webforms.
// This resolves config import conflicts when webforms have submissions.
//
// SAFETY GUARDS:
//   1. WEBCMS_SITE must be "dev" — this script will refuse to run against stage or prod.
//   2. Pass --dry-run to preview what would be deleted without touching the database.
//
// Usage:
//   node cleanup-webforms.js            # Live run (dev only)
//   node cleanup-webforms.js --dry-run  # Preview row counts, no deletes

const ecs = require("./ecs");
const ui = require("./ui");
const util = require("./util");
const vars = require("./vars");

// ---------------------------------------------------------------------------
// Safety: hard-block execution on anything that is not the dev site.
// ---------------------------------------------------------------------------
if (vars.site !== "dev") {
  console.error(
    `ERROR: cleanup-webforms.js may only run against the dev site.\n` +
    `  Current WEBCMS_SITE = "${vars.site}"\n` +
    `  Refusing to proceed to protect stage and production data.`
  );
  process.exitCode = 1;
  process.exit();
}

const isDryRun = process.argv.includes("--dry-run");

if (isDryRun) {
  ui.log("ℹ️  DRY RUN — no data will be deleted.");
  ui.log();
}

// ---------------------------------------------------------------------------
// Build the cleanup (or dry-run preview) script.
// ---------------------------------------------------------------------------

const countScript = `
  echo "--- Webform submission count ---"
  drush --uri="$WEBCMS_SITE_URL" sql:query "SELECT COUNT(*) AS submissions FROM webform_submission"
  echo "--- Webform submission data count ---"
  drush --uri="$WEBCMS_SITE_URL" sql:query "SELECT COUNT(*) AS submission_data FROM webform_submission_data"
  echo "--- Webform config count ---"
  drush --uri="$WEBCMS_SITE_URL" sql:query "SELECT COUNT(*) AS webform_configs FROM config WHERE name LIKE 'webform.webform.%'"
`;

const deleteScript = `
  echo "Deleting all webform submissions and webforms..."
  drush --uri="$WEBCMS_SITE_URL" sql:query "DELETE FROM webform_submission"
  drush --uri="$WEBCMS_SITE_URL" sql:query "DELETE FROM webform_submission_data"
  drush --uri="$WEBCMS_SITE_URL" sql:query "DELETE FROM config WHERE name LIKE 'webform.webform.%'"
  drush --uri="$WEBCMS_SITE_URL" cache:rebuild
  echo "Cleanup complete!"
`;

const cleanupScript = isDryRun ? countScript : countScript + deleteScript;

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

async function main() {
  const heading = isDryRun
    ? "Previewing webform data on dev environment (DRY RUN)"
    : "Running webform cleanup on dev environment";

  ui.logHeading("ecs", heading);

  if (!isDryRun) {
    ui.log(
      "⚠️  WARNING: This will permanently delete ALL webform submissions and webform config."
    );
    ui.log(`   Target: WEBCMS_SITE=${vars.site}  WEBCMS_ENVIRONMENT=${vars.environment}`);
    ui.log();
  }

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
      ui.log(`${isDryRun ? "Preview" : "Cleanup"} status: ${lastSeenStatus}`);
    }

    iterationCount++;
  }

  if (iterationCount >= maxIterations) {
    throw new Error(`Task did not complete within ${(maxIterations * 5) / 60} minutes`);
  }

  const info = util.inspectDrushStatus(finalStatus);

  ui.log(`Task stop: ${info.stop}`);
  ui.log(`Exit: ${info.exit}`);
  if (info.signal) {
    ui.log(`  NOTE: Task exited from signal ${info.signal}`);
  }

  ui.log();

  const logsUrl = await util.getLogsUrl(task);
  ui.log(ui.link(logsUrl, `Task logs`));

  if (!info.success) {
    throw new Error("Task did not exit cleanly");
  }

  ui.log();
  if (isDryRun) {
    ui.log("✅ Dry run complete — see row counts above.");
    ui.log("   Re-run without --dry-run to perform the actual deletion.");
  } else {
    ui.log("✅ Webforms and submissions successfully deleted!");
    ui.log("   You can now run the deployment pipeline.");
  }
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
