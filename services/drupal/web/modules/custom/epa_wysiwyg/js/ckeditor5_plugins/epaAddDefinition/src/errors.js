export class MultipleParagraphError extends Error {
  constructor(...args) {
    super(...args);
    this._userError = "Cannot add definitions across paragraphs";
  }
}

export class IncompleteDefinitionError extends Error {
  constructor(...args) {
    super(...args);
    this._userError =
      "No term definitions were found that exactly match your selected word or phrase";
  }
}

export class NetworkError extends Error {
  constructor(...args) {
    super(...args);
    this._userError = "Network error while looking up definitions";
  }
}
