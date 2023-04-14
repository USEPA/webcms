export class MultipleParagraphError extends Error {
<<<<<<< HEAD
  constructor(...args) {
    super(...args);
=======
  constructor(_message, ...args) {
    super("Unexpected non-text node", ...args);
>>>>>>> 28c9316ee (EPAD8-1930: Add definitions plugin)
    this._userError = "Cannot add definitions across paragraphs";
  }
}

export class IncompleteDefinitionError extends Error {
  constructor(...args) {
    super(...args);
<<<<<<< HEAD
    this._userError =
      "No term definitions were found that exactly match your selected word or phrase";
=======
    this._userError = "Can't apply definitions to multiple terms at once";
>>>>>>> 28c9316ee (EPAD8-1930: Add definitions plugin)
  }
}

export class NetworkError extends Error {
  constructor(...args) {
    super(...args);
    this._userError = "Network error while looking up definitions";
  }
}
