// @ts-check

import InsertDrupalInlineMediaCommand from "./insertdrupalinlinemediacommand";
import { getPreviewContainer } from "./utils";
import { Widget, toWidget } from "ckeditor5/src/widget";
import { Plugin } from "ckeditor5/src/core";

export default class DrupalInlineMediaEditing extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return [Widget];
  }

  constructor(editor) {
    super(editor);

    this.attrs = {
      drupalMediaAlt: "alt",
      drupalMediaEntityType: "data-entity-type",
      drupalMediaEntityUuid: "data-entity-uuid",
    };
    this.converterAttributes = [
      "drupalMediaEntityUuid",
      "drupalElementStyleViewMode",
      "drupalMediaEntityType",
      "drupalMediaAlt",
    ];
  }

  /**
   * @inheritdoc
   */
  init() {
    const options = this.editor.config.get("drupalMedia");
    if (!options) {
      return;
    }

    const { previewURL, themeError } = options;
    this.previewUrl = previewURL;
    this.labelError = Drupal.t("Preview failed");
    this.themeError =
      themeError ||
      `
      <p>${Drupal.t(
        "An error occurred while trying to preview the media. Please save your work and reload this page."
      )}<p>
    `;

    this._defineSchema();
    this._defineConverters();
    // this._defineListeners();

    this.editor.commands.add(
      "insertDrupalInlineMedia",
      new InsertDrupalInlineMediaCommand(this.editor)
    );
  }

  /**
   * Registers drupalInlineMedia as a block element in the DOM converter.
   *
   * @private
   */
  _defineSchema() {
    const schema = this.editor.model.schema;
    schema.register("drupalInlineMedia", {
      inheritAllFrom: '$inlineObject',
      allowAttributes: Object.keys(this.attrs),
    });
    // Register `<drupal-inline-media>` as a block element in the DOM converter. This
    // ensures that the DOM converter knows to handle the `<drupal-inline-media>` as a
    // block element.
    this.editor.editing.view.domConverter.blockElements.push(
      "drupal-inline-media"
    );
  }

  /**
   * Defines handling of drupal media element in the content lifecycle.
   *
   * @private
   */
  _defineConverters() {
    const conversion = this.editor.conversion;
    const metadataRepository = this.editor.plugins.get(
      "DrupalMediaMetadataRepository"
    );

    conversion
      .for("upcast")
      .elementToElement({
        view: {
          name: "drupal-inline-media",
        },
        model: "drupalInlineMedia",
      })
      .add((dispatcher) => {
        dispatcher.on(
          "element:drupal-inline-media",
          (evt, data) => {
            const [modelElement] = data.modelRange.getItems();
            metadataRepository
              .getMetadata(modelElement)
              .then((metadata) => {
                if (!modelElement) {
                  return;
                }
                // On upcast, get `drupalMediaIsImage` attribute value from media metadata
                // repository.
                this.upcastDrupalMediaIsImage(modelElement);
                // Enqueue a model change after getting modelElement.
                this.editor.model.enqueueChange(
                  { isUndoable: false },
                  (writer) => {
                    writer.setAttribute(
                      "drupalMediaType",
                      metadata.type,
                      modelElement
                    );
                  }
                );
              })
              .catch((e) => {
                // There isn't any UI indication for errors because this should be
                // always called after the Drupal Media has been upcast, which would
                // already display an error in the UI.
                console.warn(e.toString());
              });
          },
          // This converter needs to have the lowest priority to ensure that the
          // model element and its attributes have already been converted. It is only used
          // to gather metadata to make the UI tailored to the specific media entity that
          // is being dealt with.
          { priority: "lowest" }
        );
      });

    conversion.for("dataDowncast").elementToElement({
      model: "drupalInlineMedia",
      view: {
        name: "drupal-inline-media",
      },
    });
    conversion
      .for("editingDowncast")
      .elementToElement({
        model: "drupalInlineMedia",
        view: (modelElement, { writer }) => {
          const container = writer.createContainerElement("a", {
            class: "drupal-inline-media",
          });
          if (!this.previewUrl) {
            // If preview URL isn't available, insert empty preview element
            // which indicates that preview couldn't be loaded.
            const mediaPreview = writer.createRawElement("span", {
              "data-drupal-inline-media-preview": "unavailable",
            });
            writer.insert(writer.createPositionAt(container, 0), mediaPreview);
          }
          writer.setCustomProperty("drupalInlineMedia", true, container);

          return toWidget(container, writer, {
            label: Drupal.t("Inline Media widget"),
          });
        },
      })
      .add((dispatcher) => {
        const converter = (event, data, conversionApi) => {
          const viewWriter = conversionApi.writer;
          const modelElement = data.item;
          const container = conversionApi.mapper.toViewElement(data.item);

          // Search for preview container recursively from its children because
          // the preview container could be wrapped with an element such as
          // `<a>`.
          let media = getPreviewContainer(container.getChildren());

          // Use pre-existing media preview container if one exists. If the
          // preview element doesn't exist, create a new element.
          if (media) {
            // Stop processing if media preview is unavailable or a preview is
            // already loading.
            if (
              media.getAttribute("data-drupal-inline-media-preview") !== "ready"
            ) {
              return;
            }

            // Preview was ready meaning that a new preview can be loaded.
            // "Change the attribute to loading to prepare for the loading of
            // the updated preview. Preview is kept intact so that it remains
            // interactable in the UI until the new preview has been rendered.
            viewWriter.setAttribute(
              "data-drupal-inline-media-preview",
              "loading",
              media
            );
          } else {
            media = viewWriter.createRawElement("span", {
              "data-drupal-inline-media-preview": "loading",
            });
            viewWriter.insert(viewWriter.createPositionAt(container, 0), media);
          }

          this._fetchPreview(modelElement)
            .then(({ label, preview }) => {
              if (!media) {
                // Nothing to do if associated preview wrapped no longer exist.
                return;
              }
              // CKEditor 5 doesn't support async view conversion. Therefore, once
              // the promise is fulfilled, the editing view needs to be modified
              // manually.
              this.editor.editing.view.change((writer) => {
                const mediaPreview = writer.createRawElement(
                  "span",
                  {
                    "data-drupal-inline-media-preview": "ready",
                    "aria-label": label,
                  },
                  (domElement) => {
                    domElement.innerHTML = preview;
                  }
                );
                // Insert the new preview before the previous preview element to
                // ensure that the location remains same even if it is wrapped
                // with another element.
                writer.insert(
                  writer.createPositionBefore(media),
                  mediaPreview
                );
                writer.remove(media);
              });
            });
        };

        // List all attributes that should trigger re-rendering of the
        // preview.
        this.converterAttributes.forEach((attribute) => {
          dispatcher.on(`attribute:${attribute}:drupalInlineMedia`, converter);
        });

        return dispatcher;
      });

    // conversion.for('editingDowncast').add((dispatcher) => {
    //   dispatcher.on(
    //     'attribute:drupalElementStyleAlign:drupalInlineMedia',
    //     (evt, data, conversionApi) => {
    //       const alignMapping = {
    //         // This is a map of CSS classes representing Drupal element styles for alignments.
    //         left: 'drupal-inline-media-style-align-left',
    //         right: 'drupal-inline-media-style-align-right',
    //         center: 'drupal-inline-media-style-align-center',
    //       };
    //       const viewElement = conversionApi.mapper.toViewElement(data.item);
    //       const viewWriter = conversionApi.writer;
    //
    //       // If the prior value is alignment related, it should be removed
    //       // whether or not the module property is consumed.
    //       if (alignMapping[data.attributeOldValue]) {
    //         viewWriter.removeClass(
    //           alignMapping[data.attributeOldValue],
    //           viewElement,
    //         );
    //       }
    //
    //       // If the new value is not alignment related, do not proceed.
    //       if (!alignMapping[data.attributeNewValue]) {
    //         return;
    //       }
    //
    //       // The model property is already consumed, do not proceed.
    //       if (!conversionApi.consumable.consume(data.item, evt.name)) {
    //         return;
    //       }
    //
    //       // Add the alignment class in the view that corresponds to the value
    //       // of the model's drupalElementStyle property.
    //       viewWriter.addClass(
    //         alignMapping[data.attributeNewValue],
    //         viewElement,
    //       );
    //     },
    //   );
    // });

    // Set attributeToAttribute conversion for all supported attributes.
    Object.keys(this.attrs).forEach((modelKey) => {
      const attributeMapping = {
        model: {
          key: modelKey,
          name: "drupalInlineMedia",
        },
        view: {
          name: "drupal-inline-media",
          key: this.attrs[modelKey],
        },
      };
      // Attributes should be rendered only in dataDowncast to avoid having
      // unfiltered data-attributes on the Drupal Media widget.
      conversion.for("dataDowncast").attributeToAttribute(attributeMapping);
      conversion.for("upcast").attributeToAttribute(attributeMapping);
    });
  }

  async _fetchPreview(element) {
    const search = new URLSearchParams({
      text: this._renderElement(element),
      uuid: element.getAttribute("drupalMediaEntityUuid"),
    });

    const response = await fetch(`${this.previewUrl}?${search}`, {
      headers: {
        "X-Drupal-MediaPreview-CSRF-Token":
          this.editor.config.get("drupalMedia").previewCsrfToken,
      },
    });

    if (response.ok) {
      return {
        label: response.headers.get("drupal-media-label"),
        preview: await response.text(),
      };
    }

    return { label: this.labelError, preview: this.themeError };
  }

  _renderElement(element) {
    const fragment = this.editor.model.change((writer) => {
      const fragment = writer.createDocumentFragment();
      const target = writer.cloneElement(element, false);

      ["linkHref"].forEach((e) => {
        writer.removeAttribute(e, target);
      });

      writer.append(target, fragment);

      return fragment;
    });

    return this.editor.data.stringify(fragment);
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return "DrupalInlineMediaEditing";
  }
}

//
