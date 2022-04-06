// @ts-check

/**
 * @module
 */

/**
 * Helper function to read a required environment variable from `process.env`. If the
 * variable is either `undefined` or the empty string, an error is immediately thrown.
 *
 * @param {string} key The environment variable name
 * @return {string}
 *
 * @package
 */
function getenv(key) {
  const value = process.env[key];
  if (!value) {
    // Be detailed about the failure reason
    const state = value === "" ? "empty" : "not present";
    throw new Error(`The required environment variable $${key} is ${state}`);
  }

  return value;
}

/**
 * The environment this script is targeting (e.g., preproduction).
 *
 * Corresponds to `$WEBCMS_ENVIRONMENT` in CI/CD files.
 *
 * @constant
 * @public
 */
exports.environment = getenv("WEBCMS_ENVIRONMENT");

/**
 * The site this script is targeting (e.g., dev).
 *
 * Corresponds to `$WEBCMS_SITE` in CI/CD files.
 *
 * @constant
 * @public
 */
exports.site = getenv("WEBCMS_SITE");

/**
 * The language this script is targeting, expressed as a two-letter language code (e.g., en).
 *
 * Corresponds to `$WEBCMS_LANG` in CI/CD files.
 *
 * @constant
 * @public
 */
exports.lang = getenv("WEBCMS_LANG");

/**
 * The image tag corresponding to this build. This can be used as metadata to associate
 * a resource with a specific deployment.
 *
 * Corresponds to `$WEBCMS_IMAGE_TAG` in CI/CD files.
 *
 * @constant
 * @public
 */
exports.imageTag = getenv("WEBCMS_IMAGE_TAG");

/**
 * The AWS region in which this script is running.
 *
 * Corresponds to `$AWS_REGION`, `$AWS_DEFAULT_REGION`, or a default of `"us-east-1"`.
 *
 * @constant
 * @public
 */
exports.region =
  process.env.AWS_REGION ?? process.env.AWS_DEFAULT_REGION ?? "us-east-1";
