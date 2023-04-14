export class MultipleParagraphError extends Error {
  constructor(_message, ...args) {
    super("Unexpected non-text node", ...args);
    this._userError = "Cannot add definitions across paragraphs";
  }
}

export class IncompleteDefinitionError extends Error {
  constructor(...args) {
    super(...args);
    this._userError = "Can't apply definitions to multiple terms at once";
  }
}

export class NetworkError extends Error {
  constructor(...args) {
    super(...args);
    this._userError = "Network error while looking up definitions";
  }
}
