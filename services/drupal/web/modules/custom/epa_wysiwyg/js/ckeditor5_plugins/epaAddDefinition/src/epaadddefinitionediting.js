import { Plugin } from "ckeditor5/src/core";
import { Widget, toWidget } from "ckeditor5/src/widget";

// Space-separated list of CSS classes to apply to various template elements.
// Used in both downcast (editor->HTML) and upcast (HTML->editor) conversions.
const CSS_DEFINITION = "definition js-definition";
const CSS_TRIGGER = "definition__trigger js-definition__trigger";
const CSS_TOOLTIP = "definition__tooltip js-definition__tooltip";
const CSS_TERM = "definition__term";

// Model name
const EPA_DEFINITION = "epaDefinition";

// Attribute: term text (e.g., "EPA", "toxic", etc.)
const ATTR_TERM = "term";

// Attribute: term definition (e.g., "the U.S. Environmental Protection Agency", etc.)
const ATTR_DEFINITION = "definition";

export default class EpaAddDefinitionEditing extends Plugin {
  static get requires() {
    return [Widget];
  }

  init() {
    this._defineSchema();
    this._defineConverters();
  }

  _defineSchema() {
    this.editor.model.schema.register(EPA_DEFINITION, {
      inheritAllFrom: "$inlineObject",
      allowAttributes: [ATTR_TERM, ATTR_DEFINITION],
    });
  }

  _defineConverters() {
    const conversion = this.editor.conversion;

    // Editing downcast: converts model data to a "lightweight" CKEditor
    // presentation. In this case, we render a plain `<dfn>` element with a
    // title: users can hover over the term and see its definition as a browser
    // tooltip.
    conversion.for("editingDowncast").elementToElement({
      model: EPA_DEFINITION,
      view: (modelElement, conversion) => {
        const { writer } = conversion;

        const term = modelElement.getAttribute(ATTR_TERM) || "";
        const definition = modelElement.getAttribute(ATTR_DEFINITION) || "";

        const element = writer.createContainerElement(
          "dfn",
          { title: definition, class: CSS_TRIGGER },
          [writer.createText(term)]
        );

        // Wrap the element with a widget: this forces CKEditor to treat this as
        // a small block-like element and won't let the user move the cursor
        // into the text.
        return toWidget(element, writer, { label: "term definition display" });
      },
    });

    // Data downcast: converts model data into full HTML. This uses a more
    // elaborate HTML template (see below) and is rendered only when HTML
    // content is requested from the CKEditor instance.
    //
    // Template structure:
    //
    // ```
    // <span class="definition js-definition">
    //   <button class="definition__trigger js-definition__trigger">
    //     ${term}
    //   </button>
    //   <span role="tooltip" class="definition__tooltip js-definition__tooltip">
    //     <dfn class="definition__term">
    //       ${term}
    //     </dfn>
    //     ${definition}
    //   </span>
    // </span>
    // ```
    conversion.for("dataDowncast").elementToStructure({
      model: EPA_DEFINITION,
      view: (modelElement, { writer }) => {
        const term = modelElement.getAttribute(ATTR_TERM) || "";
        const definition = modelElement.getAttribute(ATTR_DEFINITION) || "";

        // Create a small hypertext-style helper in order to lighten the syntactic weight of the below
        const h = writer.createContainerElement.bind(writer);

        return h("span", { class: CSS_DEFINITION }, [
          h("button", { class: CSS_TRIGGER }, [writer.createText(term)]),
          h("span", { class: CSS_TOOLTIP, role: "tooltip" }, [
            h("dfn", { class: CSS_TERM }, [writer.createText(term)]),
            writer.createText(definition),
          ]),
        ]);
      },
    });

    // Upcast: convert DOM nodes into model data. CKEditor does not have helpers
    // for element-to-structure conversion, so we have to use this lower-level
    // API by listening for all <span> elements and determining which one(s) are
    // ours.
    conversion.for("upcast").add((dispatcher) => {
      dispatcher.on("element:span", (_event, data, api) => {
        const { viewItem } = data;
        const { consumable, writer, safeInsert, updateConversionResult } = api;

        // Notation: foo is the CKEditor node, fooConsumable is its consumable
        // wrapper (used at the end of this function) after validation.

        // How this upcast works:
        //
        // We proceed in roughly outermost-to-innermost fashion for each element
        // in the template. They have been annotated with numbers that can be
        // cross-referenced as we get to the steps:
        //
        // ```
        // [1] <span class="definition js-definition">
        // [2]   <button class="definition__trigger js-definition__trigger">
        //         ${term}
        //       </button>
        // [3]   <span role="tooltip" class="definition__tooltip js-definition__tooltip">
        // [4]     <dfn class="definition__term">
        // [5]       ${term}
        //         </dfn>
        // [6]     ${definition}
        //       </span>
        //     </span>
        // ```
        //
        // Note that in this process, we have arbitrarily chosen to only
        // validate one instance of the term, inside the `<dfn>` element. We
        // assume that if the structure is otherwise correct, the button's text
        // content doesn't need to be validated since it is duplicative.

        // [1] Make sure we have a span.definition. This is the outermost part
        // of the template.
        const viewItemConsumable = {
          name: true,
          classes: CSS_DEFINITION.split(" "),
        };

        if (!consumable.test(viewItem, viewItemConsumable)) {
          return;
        }

        // [1] Validate that it has two children.
        if (viewItem.childCount !== 2) {
          return;
        }

        // [2] Validate the button.definition__trigger that should be the first
        // child of the definition span.
        const triggerButton = viewItem.getChild(0);
        const triggerButtonConsumable = {
          name: true,
          classes: CSS_TRIGGER.split(" "),
        };

        const isTriggerButton =
          triggerButton.is("element", "button") &&
          consumable.test(triggerButton, triggerButtonConsumable);

        if (!isTriggerButton) {
          return;
        }

        // [3] Validate the span.definition__tooltip that should be the next child of the definition span.
        const tooltipSpan = viewItem.getChild(1);
        const tooltipSpanConsumable = {
          name: true,
          classes: CSS_TOOLTIP.split(" "),
          attributes: ["role"],
        };

        const isTooltipSpan =
          tooltipSpan.is("element", "span") &&
          consumable.test(tooltipSpan, tooltipSpanConsumable);

        if (!isTooltipSpan) {
          return;
        }

        // [3] Validate that the tooltip span has two children.
        if (tooltipSpan.childCount !== 2) {
          return;
        }

        // [4] Validate the dfn.definition__term structure that should be the
        // first child of the tooltip span.
        const termDfn = tooltipSpan.getChild(0);
        const termDfnConsumable = {
          name: true,
          classes: CSS_TERM.split(" "),
        };

        const isTermSpan =
          termDfn.is("element", "dfn") &&
          consumable.test(termDfn, termDfnConsumable);
        if (!isTermSpan) {
          return;
        }

        // [4] Validate that the term element has only one child.
        if (termDfn.childCount !== 1) {
          return;
        }

        // [5] Grab the term's first child and ensure it is a text element.
        const termText = termDfn.getChild(0);
        if (!termText.is("$text")) {
          return;
        }

        // [6] Grab the _second_ child of the tooltip span and ensure it is the
        // expected text node.
        const definitionText = tooltipSpan.getChild(1);
        if (!definitionText.is("$text")) {
          return;
        }

        // Now that the template structure has been validated, create the inline
        // model element.
        const modelElement = writer.createElement(EPA_DEFINITION, {
          [ATTR_TERM]: termText.data,
          [ATTR_DEFINITION]: definitionText.data,
        });

        // Attempt to insert the new element
        if (!safeInsert(modelElement, data.modelCursor)) {
          return;
        }

        // Tell CKEditor we've consumed these nodes and their classes/attributes
        consumable.consume(viewItem, viewItemConsumable);
        consumable.consume(triggerButton, triggerButtonConsumable);
        consumable.consume(tooltipSpan, tooltipSpanConsumable);
        consumable.consume(termDfn, termDfnConsumable);

        // Done.
        updateConversionResult(modelElement, data);
      });
    });
  }
}
