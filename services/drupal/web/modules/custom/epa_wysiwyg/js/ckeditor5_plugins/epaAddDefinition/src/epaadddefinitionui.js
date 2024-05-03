import { Plugin } from "ckeditor5/src/core";
import { ButtonView, Notification } from "ckeditor5/src/ui";
import lookupTerms from "./lookupterms";
import { IncompleteDefinitionError, MultipleParagraphError } from "./errors";
import EpaAddDefinitionView from "./epaadddefinitionview";
import icon from '../../../../icons/book.svg';

const ERR_UNEXPECTED = "An unexpected error occurred";
const LOCK_ID = "epaAddDefinitions";

export default class EpaAddDefinitionUI extends Plugin {
  static get requires() {
    return [Notification];
  }

  static get pluginName() {
    return "EpaAddDefinitionUI";
  }

  init() {
    const editor = this.editor;

    this.modalView = new EpaAddDefinitionView(this.editor.locale);

    editor.ui.componentFactory.add("epaAddDefinition", (locale) => {
      const buttonView = new ButtonView(locale);

      buttonView.set({
        label: editor.t("Add Definition"),
        icon,
        tooltip: true,
      });

      buttonView
        .bind("isEnabled")
        .to(editor, "isReadOnly", (readOnly) => !readOnly);

      this.listenTo(buttonView, "execute", () => {
        this._execute().catch((error) => {
          const notification = this.editor.plugins.get(Notification);
          const message = error._userError || ERR_UNEXPECTED;
          notification.showWarning(message, {
            namespace: "epa:addDefinition",
          });

          console.error(error);
        });
      });

      return buttonView;
    });
  }

  async _execute() {
    const modal = this.modalView;
    if (modal && !modal.isRendered) {
      modal.render();
      document.body.appendChild(modal.element);
    }

    const model = this.editor.model;
    const selection = model.document.selection;
    const range = selection.getFirstRange();

    // Skip if there's no selection
    if (range && range.isCollapsed) {
      return;
    }

    const userInput = range && Array.from(range.getItems()).reduce((acc, node) => {
      if (node.is("$text") || node.is("$textProxy")) {
        return acc + node.data;
      }
      // Crash if we found a non-text node in the selection
      throw new MultipleParagraphError();
    }, "");

    // Skip if there's no meaningful text highlighted
    if (userInput && userInput.trim() === "") {
      return;
    }

    let modalResult = null;

    /** @type {import("./lookupterms").LookupResult | null} */
    let lookupResult = null;

    try {
      if (!modal) {
        throw new Error("Modal not initialized");
      }
      // Lock the editor as read-only while the user makes selections
      this.editor.enableReadOnlyMode(LOCK_ID);

        lookupResult = await lookupTerms(userInput);
        if (lookupResult === null) {
          return;
        }

      const result = lookupResult ? lookupResult.matches : null;

      if (!result) {
        throw new IncompleteDefinitionError(
          `Could not find a term that matches '${userInput}'`
        );
      }

      modal.data = result;

      modal.show();

      modalResult = await new Promise((resolve) => {
        let resolved;

        /** @param {boolean} resolution */
        function createResolve(resolution) {
          return () => {
            // The 'close' event will fire regardless of the user accepting the
            // definition (due to us binding the event to "cancel"), so this
            // check prevents us from double-resolving the promise.
            if (resolved) {
              return;
            }

            resolve(resolution);
            resolved = true;
          };
        }

        // Whichever of these events fires first wins: the "submit" event will
        // fire first since, in responding to it, we request a modal close.
        modal.on("submit", createResolve(true));
        modal.on("cancel", createResolve(false));
      });
    } finally {
      if (!modal) {
        throw new Error("Modal not initialized");
      }
      this.editor.disableReadOnlyMode(LOCK_ID);
      modal.hide();
    }

    // Modal was canceled, so no action should be taken
    if (!modalResult) {
      return;
    }

    // This is the array of all the MatchViews for all the terms
    const SelectedArray = modal.listView.views._items;

    // Iterate over the selected items and create and store a marker for each one
    const wordMaps = [];
    model.change((writer) => {
      modal.data.forEach(obj => {
        const index = obj.index[0];
        const term = obj.term;
        const startPosition = model.createPositionAt(selection.getFirstRange().start.parent, index);
        const endPosition = model.createPositionAt(selection.getFirstRange().start.parent, index + term.length);
        const wordRange = model.createRange(startPosition, endPosition);
        const marker = writer.addMarker(`${index}: ${term}`, { range: wordRange, usingOperation: true });
        wordMaps.push({
          term: term,
          range: wordRange,
          marker: marker,
        });
      });
    });

    // this only happens after a definition is selected and _confirmed_ by clicking the green check mark button
    if (SelectedArray) {
      model.change((writer) => {

        for (const i in SelectedArray) {
          if (SelectedArray[i].selected) {
            const wordMapMatch = wordMaps.find(word => word.term === SelectedArray[i].term);
            const wordRange = wordMapMatch.marker.getRange();
            writer.remove(wordRange);
            writer.insertElement(
              "epaDefinition",
              {
                term: SelectedArray[i].term,
                definition: SelectedArray[i].selected
              },
              wordRange.start,
            );
          }
        }

      });
    }

    // Clean up all the markers
    model.change((writer) => {
      wordMaps.forEach(obj => {
        writer.removeMarker(obj.marker);
      });
    });

  }
}
