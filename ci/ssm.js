// @ts-check

/**
 * @module
 */

const { SSMClient, GetParameterCommand } = require("@aws-sdk/client-ssm");

const vars = require("./vars");

const client = new SSMClient({ region: vars.region });

/**
 * A cache of Parameter Store names to their values, used to memoize the `getParameter()`
 * function below.
 *
 * This cache is unbounded, but will not cause a memory link because this script only
 * loads a small, finite number of parameters from AWS.
 *
 * @type {Record<string, string>}
 *
 * @package
 */
const cache = Object.create(null);

/**
 * Fetches a parameter from AWS Parameter Store.
 *
 * This auguments the raw GetParameter API with two conveniences:
 * 1. This function automatically prepends the `/webcms/${var.environment}` path prefix to
 *    the suffix passed in to this function.
 * 2. Parameter values are memoized in order to avoid paying the penalty of repeated reads
 *    of the samae parameter.
 *
 * @param {string} suffix The parameter path within this environment's Parameter Store
 * hierarchy.
 *
 * @return {Promise<string>} The parameter's value
 *
 * @public
 */
async function getParameter(suffix) {
  // Construct the full parameter path per convention
  const name = `/webcms/${vars.environment}/${suffix}`;

  // Check the cache - if there's a hit, don't do anything else.
  const cached = cache[name];
  if (cached !== undefined) {
    return cached;
  }

  // Otherwise, fetch the parameter from AWS...
  const command = new GetParameterCommand({ Name: name });
  const response = await client.send(command);

  // ... and update the cache with the returned value.
  return (cache[name] = response.Parameter.Value);
}

exports.getParameter = getParameter;
