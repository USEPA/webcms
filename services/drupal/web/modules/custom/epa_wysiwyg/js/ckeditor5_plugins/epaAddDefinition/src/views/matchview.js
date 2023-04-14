<<<<<<< HEAD
import { View, Template } from "ckeditor5/src/ui";
=======
import { View } from "ckeditor5/src/ui";

>>>>>>> 28c9316ee (EPAD8-1930: Add definitions plugin)
import createId from "./createid";

/**
 * @template {keyof HTMLElementTagNameMap} K
 *
 * @param {K} tag
 * @param {Record<string, string>} attrs
 * @param {Array<string | Element>} children
 *
 * @returns {HTMLElementTagNameMap[K]}
 */
function h(tag, attrs, ...children) {
  const element = document.createElement(tag);

  for (const [name, value] of Object.entries(attrs)) {
    element.setAttribute(name, value);
  }

  for (const child of children) {
    const node =
      typeof child === "string" ? document.createTextNode(child) : child;

    element.appendChild(node);
  }

  return element;
}

export default class MatchView extends View {
  constructor(locale) {
    super(locale);

    this.set("term", "");
    this.set("definitions", []);
    this.set("selected", "");

    const bind = this.bindTemplate;

    this.selectId = createId();

    this.select = h("select", { id: this.selectId });

<<<<<<< HEAD
    this.selectedTextFull = new Template({
      tag: "p",
      attributes: {
        class: "epa-add-def-selected",
      },
      children: [{ text: bind.to("selected") }],
    });

=======
>>>>>>> 28c9316ee (EPAD8-1930: Add definitions plugin)
    this.setTemplate({
      tag: "div",
      attributes: {
        class: ["epa-add-def__match"],
      },
      children: [
        {
          tag: "label",
          attributes: { for: this.selectId },
<<<<<<< HEAD
          children: [
            { text: "Term: " },
            new Template({
              tag: "strong",
              children: [{ text: bind.to("term") }],
            }),
          ],
        },
        this.select,
        this.selectedTextFull,
=======
          children: [{ text: "Term: " }, { text: bind.to("term") }],
        },
        this.select,
>>>>>>> 28c9316ee (EPAD8-1930: Add definitions plugin)
      ],
      on: {
        [`change@select#${this.selectId}`]: bind.to(() => {
          const index = this.select.selectedIndex;

          const selection =
            index === 0 ? "" : this.definitions[index - 1].definition;
<<<<<<< HEAD
=======
          console.log("setting selection to", selection);
>>>>>>> 28c9316ee (EPAD8-1930: Add definitions plugin)
          this.selected = selection;
        }),
      },
    });

    this.on("change:definitions", (_event, _property, data) => {
      const options = this.select.options;
      options.length = 0;

      options.add(h("option", { value: "" }, ""));

      for (const item of data) {
<<<<<<< HEAD
        const MAX_LENGTH = 125;
        const value = `${item.dictionary} -- ${item.definition}`;
        const truncated = value.substring(0, MAX_LENGTH);
        const label = value.length >= MAX_LENGTH ? truncated + "..." : value;
        options.add(h("option", { value: value }, label));
=======
        const label = `${item.definition} (${item.dictionary})`;
        options.add(h("option", { value: item.definition }, label));
>>>>>>> 28c9316ee (EPAD8-1930: Add definitions plugin)
      }
    });
  }
}
