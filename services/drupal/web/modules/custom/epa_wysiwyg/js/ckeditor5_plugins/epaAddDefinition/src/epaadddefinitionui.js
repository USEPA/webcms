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
    console.log({ selection });
    const range = selection.getFirstRange();
    // console.log('range: ', range);
    // range.end.offset gives the index at which the selection ends _within_ the parent node
    // console.log('getRanges()', selection.getRanges());

    // Skip if there's no selection
    if (range && range.isCollapsed) {
      return;
    }

    const userInput = range && Array.from(range.getItems()).reduce((acc, node) => {
      if (node.is("$text") || node.is("$textProxy")) {
        // console.log({ node });
        return acc + node.data;
      }

      // Crash if we found a non-text node in the selection
      throw new MultipleParagraphError();
    }, "");

    // Skip if there's no meaningful text highlighted
    if (userInput && userInput.trim() === "") {
      return;
    }

    const userInputList = userInput && userInput
      .replaceAll(/[?!.]+/g, "")
      .split(" ")
      .filter((word) => word !== " " && word !== "")
      .join(", ");
    // console.log('📠', userInputList);

    // the start and end Position for each word the use highlighted (this will be used to generate the Range)
    const words = userInputList.split(", ");
    const positions = [];
    words.forEach(word => {
      const index = userInput.indexOf(word);
      const startPosition = model.createPositionAt(model.document.getRoot(), index);
      const endPosition = model.createPositionAt(model.document.getRoot(), index + word.length);
      positions.push({start: startPosition, end: endPosition});
    });
    console.log('positions: ', positions);

    let modalResult = null;

    /** @type {import("./lookupterms").LookupResult | null} */
    let lookupResult = null;

    try {
      if (!modal) {
        throw new Error("Modal not initialized");
      }
      // Lock the editor as read-only while the user makes selections
      this.editor.enableReadOnlyMode(LOCK_ID);

      if (userInputList) {
        lookupResult = await lookupTerms(userInputList);
        if (lookupResult === null) {
          return;
        }
      }

      // const result = lookupResult.matches.find(
      //   (match) => match.term === userInput.toLowerCase()
      // );

      // what if we just change it to this?
      const result = lookupResult ? lookupResult.matches : null;
      // That works!  Except, now the markup is applied to the entire selection, not just the individual words.

      if (!result) {
        throw new IncompleteDefinitionError(
          `Could not find a term that matches '${userInput}'`
        );
      }

      modal.data = result;
      // now instead of returning one set of results as an array, we return the entire array of results, so we don't need to wrap it in an array anymore
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

    const selected = modal.listView.views.get(0);

    // This is the array of all the MatchViews for all the terms
    console.log('SelectedArray', modal.listView.views._items)
    // So, we need to loop through the SelectedArray, and apply the model.change() block for each one.
    // How do we get range for each one?
    // Option 1. Use a Marker Collection. Hmmmm....
    // Option 2. Compare each term to the original string (userInput) and find the range for each one relative to that.

    console.log('selected: ', selected);

    // this only happens after a definition is selected and _confirmed_ by clicking the green check mark button
    if (range && selected) {
      // selected.definitions is an array of the definitions for only the *first* term in the list
      // selected.selected is the definition that was selected
      model.change((writer) => {
        // TODO: this block will need to be modified too, since term should not be userInput, but an individual word/term from the userInputList (`SelectedArray[i].term`), and definition should be one of the definitions (`SelectedArray[i].selected`).  In both of these, `i` is the index of the term in the userInputList
        // TODO: call this block once for each term in the userInputList. Find the definition that matches the term
        writer.remove(range);
        // TODO: range will need to be evaluated differently.  Maybe we can use createRange https://ckeditor.com/docs/ckeditor5/latest/api/module_engine_model_model-Model.html#function-createRange
        // but we still need to pass it Positions.  We can get those from createPosition()
        writer.insertElement(
          "epaDefinition",
          { term: userInput, definition: selected.selected },
          range.start
        );
      });
    }
  }
}
