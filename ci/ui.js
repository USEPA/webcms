// @ts-check

/**
 * @module
 */

const isBuildkite = process.env.BUILDKITE === "true";

/**
 * Creates an interactive hyperlink using Buildkite's formatting.
 *
 * cf. the {@link https://buildkite.com/docs/pipelines/links-and-images-in-log-output Buildkite docs}
 *
 * @param {string} url The url to link to
 * @param {string=} content Text to use as the link
 * @return {string}
 *
 * @package
 */
function buildkiteLink(url, content) {
  let link = `url='${url}'`;
  if (content) {
    link += `;content='${content}'`;
  }

  return `\x1b]1339;${link}\x07`;
}

/**
 * Default function to create a hyperlink. If a CI/CD runner does not have specialized
 * support for hyperlinks, we output the URL. If the `content` argument is provided, then
 * we output a Markdown-style link like `[text]: url`.
 *
 * @param {string} url The url to link to
 * @param {string=} content Text to use as the link
 * @return {string}
 *
 * @package
 */
function defaultLink(url, content) {
  return content ? `[${content}]: ${url}` : url;
}

/**
 * Creates a hyperlink. If the CI/CD runner supports it, then the text is formatted with
 * special control codes to make the link interactive.
 *
 * @function
 * @param {string} url The url to link to
 * @param {string=} content The content of the link
 * @return {string}
 *
 * @public
 */
const link = isBuildkite ? buildkiteLink : defaultLink;

/**
 * Write a message to standard output. A newline is added to the message automatically and
 * should be omitted by callers of this function.
 *
 * @param {string=} message The message to write
 *
 * @public
 */
function log(message = "") {
  process.stdout.write(message + "\n");
}

/**
 * Outputs a section heading using Buildkite's emoji support.
 *
 * @param {string} emoji The {@link https://github.com/buildkite/emojis Buildkite emoji} to display
 * @param {string} heading The section's heading text
 *
 * @package
 */
function logBuildkiteHeading(emoji, heading) {
  log(`--- :${emoji}: ${heading}`);
}

/**
 * Outputs a section heading for other CI/CD runners. This function ignores the first
 * emoji parameter.
 *
 * @param {string} emoji The (unused) emoji to display
 * @param {string} heading The section's heading text
 *
 * @package
 */
function logDefaultHeading(emoji, heading) {
  log(`--- ${heading}`);
}

/**
 * Outputs a section heading, formatted in a simple `"--- <emoji> <heading>"` style. Note
 * that if the CI/CD runner does not have `:name:`-style emoji support, that parameter
 * is ignored.
 *
 * @kind function
 * @param {string} emoji The emoji to display. Not supported by all runners.
 * @param {string} heading The heading text to display.
 * @return {void}
 *
 * @public
 */
const logHeading = isBuildkite ? logBuildkiteHeading : logDefaultHeading;

/**
 * Writes a text sequence to expand a previously-collapsed section in Buildkite. See
 * [Managing Log
 * Output](https://buildkite.com/docs/pipelines/managing-log-output#collapsing-output) in
 * the Buildkite docs for more information.
 *
 * @return {void}
 *
 * @package
 */
function buildkiteNotify() {
  log("^^^ +++");
}

/**
 * Other CI/CD runners, such as GitLab, don't have support for a build opening a section.
 * In those cases, this functionality is a no-op.
 *
 * @return {void}
 *
 * @package
 */
function defaultNotify() {}

/**
 * Function to notify a CI/CD runner. This functionality varies by runner; for example,
 * in Buildkite, this opens the most recent section heading (see `logHeading`), but in
 * GitLab this does nothing.
 *
 * @function
 * @return {void}
 *
 * @public
 */
const notify = isBuildkite ? buildkiteNotify : defaultNotify;

exports.link = link;
exports.log = log;
exports.logHeading = logHeading;
exports.notify = notify;
