// @ts-check

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
    if (!modal.isRendered) {
      modal.render();
      document.body.appendChild(modal.element);
    }

    const model = this.editor.model;
    const selection = model.document.selection;
    const range = selection.getFirstRange();

    // Skip if there's no selection
    if (range.isCollapsed) {
      return;
    }

    const userInput = Array.from(range.getItems()).reduce((acc, node) => {
      if (node.is("text") || node.is("textProxy")) {
        return acc + node.data;
      }

      // Crash if we found a non-text node in the selection
      throw new MultipleParagraphError();
    }, "");

    // Skip if there's no meaningful text highlighted
    if (userInput.trim() === "") {
      return;
    }

    let modalResult = null;

    /** @type {import("./lookupterms").LookupResult | null} */
    let lookupResult = null;

    try {
      // Lock the editor as read-only while the user makes selections
      this.editor.enableReadOnlyMode(LOCK_ID);

      lookupResult = await lookupTerms(userInput);
      if (lookupResult === null) {
        return;
      }

      // We only support the case where a single term is matched by the whole
      // input, so we search for that result in the array and fail if we
      // couldn't find it.
      //
      // Two notes:
      // 1. This has the effect of limiting search results to one hit (i.e., the
      //    user cannot select a sentence or paragraph like they could in the
      //    old model).
      // 2. This will fail if the user's selection includes whitespace on either
      //    end, since repositioning the `range` variable seems to be beyond
      //    CKEditor 5's capabilities.
      const result = lookupResult.matches.find(
        (match) => match.term === userInput.toLowerCase()
      );

      if (!result) {
        throw new IncompleteDefinitionError(
          `Could not find a term that matches '${userInput}'`
        );
      }

      modal.data = [result];
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
      this.editor.disableReadOnlyMode(LOCK_ID);
      modal.hide();
    }

    // Modal was canceled, so no action should be taken
    if (!modalResult) {
      return;
    }

    const selected = modal.listView.views.get(0);

    model.change((writer) => {
      writer.remove(range);
      writer.insertElement(
        "epaDefinition",
        { term: userInput, definition: selected.selected },
        range.start
      );
    });
  }
}
