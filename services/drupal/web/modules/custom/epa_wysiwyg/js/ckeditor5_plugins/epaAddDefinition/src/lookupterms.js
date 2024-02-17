import { NetworkError } from "./errors";

const ENDPOINT = "https://termlookup.epa.gov/termlookup/v1/terms";

/**
 * @typedef {object} Definition
 * @prop {string} dictionary
 * @prop {string} definition
 */

/**
 * @typedef {object} Match
 * @prop {number[]} index
 * @prop {string} term
 * @prop {Definition[]} definitions
 */

/**
 * @typedef {object} LookupResult
 * @prop {Match[]} matches
 */

/**
 * @param {string} text
 * @returns {Promise<LookupResult>}
 */

async function lookupTerms(text) {
  const body = new URLSearchParams();
  body.set("text", text);

  const response = await fetch(ENDPOINT, {
    method: "POST",
    body,
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
  });

  if (!response.ok) {
    const error = new NetworkError(
      `Failed to look up terms: ${response.status} ${response.statusText}`
    );

    try {
      const responseText = await response.text();
      Object.assign(error, { responseText });
    } catch (_) {}

    throw error;
  }

  return response.json();
}

export default lookupTerms;
