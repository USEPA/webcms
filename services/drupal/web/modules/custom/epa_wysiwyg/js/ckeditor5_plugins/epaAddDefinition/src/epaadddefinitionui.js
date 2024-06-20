import { Plugin } from "ckeditor5/src/core";
import { ButtonView, Notification } from "ckeditor5/src/ui";
import icon from "../../../../icons/book.svg";
import EpaAddDefinitionView from "./epaadddefinitionview";
import { IncompleteDefinitionError, MultipleParagraphError } from "./errors";
import lookupTerms from "./lookupterms";

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
    if (!range || (range && range.isCollapsed)) {
      return;
    }

    const rangeItems = Array.from(range.getItems());
    const firstPosition = selection.getFirstPosition();
    const parentBlockElement = firstPosition.findAncestor("paragraph");
    const paragraphChildren = Array.from(parentBlockElement.getChildren());

    let paragraphText = ""; // collected to accurately position terms within the paragraph
    let userInput = ""; // gets sent to the API for lookup
    let offsets = [];

    function traverseSelectedRangeNode(node) {
      if (node.data) {
        userInput += node.data;
      } else if (node.name === "epaDefinition") {
        userInput += " "; // Add one space to account for the character equivalent of one epaDef node
      } else if (node.name === "paragraph") {
        // Selecting across paragraphs is currently not supported
        throw new MultipleParagraphError();
      }
    }

    function traverseParagraphNode(node) {
      if (node.data) {
        let startOffset = paragraphText.length; // first time through, this is 0
        paragraphText += node.data;
        offsets.push({
          node,
          startOffset: startOffset,
          endOffset: paragraphText.length,
        });
      } else if (node.name === "epaDefinition") {
        let startOffset = paragraphText.length;
        paragraphText += " ";
        offsets.push({
          node,
          startOffset: startOffset,
          endOffset: startOffset + paragraphText.length + 1,
        });
      } else if (node.name === "paragraph") {
        // Selecting across paragraphs is currently not supported
        throw new MultipleParagraphError();
      }
    }

    // Traverse the paragraph containing the selected range to accurately place offsets
    for (const item of paragraphChildren) {
      traverseParagraphNode(item);
    }

    // Traverse the selected range to accumulate text to send to API
    for (const item of rangeItems) {
      traverseSelectedRangeNode(item);
    }

    // Rationale: traversing the paragraph happens separately from the selection because the nodes might partially overlap, and the rangeItems[0].offsetInText will be a different number depending on whether there are definition nodes upstream, so it can't be relied upon for positioning offsets.  And we only know if the upstream text contained a definition node by first traversing the paragraph nodes.  If CKE5 has a way to get the offset which accounts for selectable characters in inline elements (such as the epaDefinition element), then this could be simplified.

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

      if (!result || result.length === 0) {
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

    const termMarkersAndRanges = [];

    model.change((writer) => {
      modal.data.forEach((obj) => {
        const term = obj.term;

        // Find the word in the accumulated text of the paragraph, without case sensitivity
        const termIndex = paragraphText.toLocaleLowerCase().indexOf(term);
        // termIndex needs to be relative to the paragraphText and not the userInput because the offsets are relative to the paragraph

        if (termIndex !== -1) {
          const termEnd = termIndex + term.length;
          const originalTerm = paragraphText.substring(termIndex, termEnd);
          const startInfo = offsets.find(
            (o) => o.startOffset <= termIndex && o.endOffset > termIndex
          );
          const endInfo = offsets.find(
            (o) => o.startOffset < termEnd && o.endOffset >= termEnd
          );
          if (startInfo && endInfo) {
            const rangeStart = writer.createPositionAt(
              startInfo.node.parent,
              termIndex
            );
            const rangeEnd = writer.createPositionAt(
              endInfo.node.parent,
              termEnd
            );
            const wordRange = writer.createRange(rangeStart, rangeEnd);
            const markerName = `Term: ${term} - ${Date.now()}`;
            const marker = writer.addMarker(markerName, {
              range: wordRange,
              usingOperation: true,
              affectsData: true,
            });

            termMarkersAndRanges.push({
              term: term,
              originalTerm: originalTerm,
              range: wordRange,
              marker: marker,
            });
          }
        }
      });
    });

    // this only happens after a definition is selected and _confirmed_ by clicking the green check mark button
    if (SelectedArray && termMarkersAndRanges.length > 0) {
      model.change((writer) => {
        for (const i in SelectedArray) {
          if (SelectedArray[i].selected) {
            const wordMapMatch = termMarkersAndRanges.find(
              (word) => word.term === SelectedArray[i].term
            );
            const wordRange = wordMapMatch.range;
            writer.remove(wordRange);
            writer.insertElement(
              "epaDefinition",
              {
                term: wordMapMatch.originalTerm, // Use the original term from the text to preserve case
                definition: SelectedArray[i].selected,
              },
              wordRange.start
            );
          }
        }
      });
    }

    model.change((writer) => {
      // Clean up all the markers
      termMarkersAndRanges.forEach((obj) => {
        writer.removeMarker(obj.marker);
      });

      // Clear out the MatchViews so the next query doesn't get confused
      modal.listView.views.clear();
    });
  }
}
