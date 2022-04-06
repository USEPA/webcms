// @ts-check

/**
 * @module
 */

const { signals } = require("os").constants;
const { posix } = require("path");

const ecs = require("./ecs");
const ssm = require("./ssm");
const vars = require("./vars");

/**
 * Amount of time, in milliseconds, to wait in between checking Drush's status in ECS.
 */
const pollInterval = 5_000;

/**
 * Returns a `Promise` that waits for a fixed polling interval.
 *
 * @return {Promise<void>}
 */
async function delay() {
  return new Promise((resolve) => {
    setTimeout(resolve, pollInterval);
  });
}

/**
 * A cleaned-up version of the raw `Status` object from the ecs module. The fields on this
 * object are intended for human (not machine) consumption and should be used only for
 * display purposes.
 *
 * @typedef {object} ExitInfo
 *
 * @prop {string} stop Information about the overall task's stop reason.
 * @prop {string} exit Information, if any, about the exit status of the Drush container.
 * @prop {string=} signal When defined, this is the name of the signal that killed the
 * Drush container.
 * @prop {boolean} success Whether or not the task should be considered to have exited
 * successfully.
 */

/**
 * Takes the raw Drush exit information (see the `Status` type) and turns it into
 * presentation information for the console. The output of this function is not useful for
 * detailed inspection (it loses concrete identifiers like the stop and exit codes), but
 * does include a `success` field if the task can be considered to have exited cleanly
 * (that is, the exitCode field is exactly 0).
 *
 * In general, the `stop` and `exit` fields use this format for output:
 * 1. If both the code and reason are available, then this field will be the string
 *    `"<code> (<reason>)"`.
 * 2. If only one of the code or reason are available, then this field will be that value.
 * 3. Otherwise, it will be the string `"Unavailable"`.
 *
 * If the container was killed by a signal, then we output the signal number and, if we
 * can determine it, the signal name. (This can help in tracking down errors caused, for
 * example, by segmentation faults, as it surfaces the underlying SIGSEGV.)
 *
 * @param {ecs.Status} status The object representing the Drush stop status.
 * @return {ExitInfo} The formatted exit information
 */
function inspectDrushStatus(status) {
  const { stopCode, stopReason, exitCode, exitReason } = status;

  /**
   * The human-readable stop code and/or reason.
   * @type {string=}
   */
  let stop;

  /**
   * The human-readable exit code and/or reason.
   * @type {string=}
   */
  let exit;

  /**
   * The human-readable signal information.
   * @type {string=}
   */
  let signal;

  // Determine which (if any) of the stop code/reason fields were returned by the ECS API.
  // (In the documentation, both of these fields are optional, so we need to guard against
  // all cases.)
  if (stopCode && stopReason) {
    stop = `${stopCode} (${stopReason})`;
  } else if (stopCode) {
    stop = stopCode;
  } else if (stopReason) {
    stop = stopReason;
  }

  // If we have an exit code in the status, determine some more information. Note that we
  // use the stricter `!== undefined` check because `if (exitCode)` would return false for
  // the value 0, which is not what we want.
  if (exitCode !== undefined) {
    exit = exitReason ? `${exitCode} (${exitReason})` : String(exitCode);

    // An exit code above 128 indicates the process was killed by a signal.
    if (exitCode > 128) {
      // The os.constants.signals object is an object of { [signal name]: signal number },
      // so we have to use Object.entries() to find the name.
      const signalNumber = exitCode - 128;
      const signalName = Object.entries(signals).find(
        (sig) => sig[1] === signalNumber
      )?.[0];

      signal = signalName
        ? `${signalNumber} (${signalName})`
        : String(signalNumber);
    }
  } else if (exitReason) {
    // If we only had an exit reason (usually this is due to ECS failing to start a
    // container), use that.
    exit = exitReason;
  }

  return {
    // If either of the stop or exit fields was not set, give it a default value.
    stop: stop ?? "Unavailable",
    exit: exit ?? "Unavailable",
    signal,

    success: exitCode === 0,
  };
}

/**
 * Sanitizes slashes in a string in the same manner that the AWS CloudWatch console does.
 *
 * @param {string} text The text to escape
 * @return {string} The escaped text
 */
function escapeSlashes(text) {
  return text.replace(/\//g, "$252F");
}

/**
 * Returns a direct URL to the Drush logs in CloudWatch.
 *
 * @param {string} task The ARN of the Drush task.
 * @return {Promise<string>} The logs URL
 */
async function getLogsUrl(task) {
  // Log streams are made unique by using the task ARN's hexadecimal identifier. (This is
  // the same hex identifier in the ECS Console.)
  const id = task.split("/").pop();

  // Fargate's convention for log groups has the string "drush" appearing twice: one
  // matches up with the container name, but it's uncertain as to why there's a second.
  const logStream = `drush/drush/${id}`;

  // Read the log group name from Parameter Store
  const logGroup = await ssm.getParameter(
    `/webcms/${vars.environment}/${vars.site}/${vars.lang}/log-groups/drush`
  );

  // Use the posix helper from the path module to simplify the process of constructing
  // the AJAX path to the log group.
  const logPath = posix.join(
    "log-groups/log-group",
    escapeSlashes(logGroup),
    "log-events",
    escapeSlashes(logStream)
  );

  // Start with the region-specific AWS Console URL.
  const url = new URL(
    `https://${vars.region}.console.amazon.com/cloudwatch/home`
  );

  // We also set the region via query parameters, to mimic the behavior of the console.
  url.searchParams.set("region", vars.region);

  // Set the AJAX path.
  url.hash = `logsV2:${logPath}`;

  return String(url);
}

/**
 * @param {string} task
 */
async function getTaskUrl(task) {
  // Fetch the cluster name from Parameter Store.
  const cluster = await ssm.getParameter("ecs/cluster-name");

  const id = task.split("/").pop();

  // As above, start with the region-specific console URL and set the ?region query string
  // parameter.
  const url = new URL(`https://${vars.region}.console.aws.amazon.com`);
  url.searchParams.set("region", vars.region);

  // Construct the path to the task's landing page in the V2 ECS console (at the time of
  // writing, AWS calls this the "New ECS Experience").
  url.pathname = posix.join(
    "ecs/v2/clusters",
    cluster,
    "tasks",
    id,
    "configuration"
  );

  return String(url);
}

exports.delay = delay;
exports.inspectDrushStatus = inspectDrushStatus;
exports.getLogsUrl = getLogsUrl;
exports.getTaskUrl = getTaskUrl;
