import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';
import { isDrupalMedia } from "../../../../../../../core/modules/ckeditor5/js/ckeditor5_plugins/drupalMedia/src/utils";
import { isDrupalInlineMedia } from "../../../../../../contrib/media_inline_embed/js/ckeditor5_plugins/mediaInlineEmbed/src/utils";
import linkIcon from "../../../../icons/copy-solid.svg";

export default class EpaFileUrlUI extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return ['DrupalMediaEditing'];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'CopyFilepath';
  }

  init() {
    this._createToolbarCopyFilepathButton();
  }

  /**
   * Creates a `DrupalLinkMediaUI` button view.
   *
   * Clicking this button shows a {@link module:link/linkui~LinkUI#_balloon}
   * attached to the selection. When an media is already linked, the view shows
   * {@link module:link/linkui~LinkUI#actionsView} or
   * {@link module:link/linkui~LinkUI#formView} if it is not.
   */
  _createToolbarCopyFilepathButton() {
    const { editor } = this;

    editor.ui.componentFactory.add('CopyFilepathButton', (locale) => {
      const button = new ButtonView(locale);
      // const linkCommand = editor.commands.get('EditDrupalMedia');

      button.set({
        isEnabled: true,
        label: Drupal.t('Copy file path'),
        icon: linkIcon,
        tooltip: true,
      });

      this.listenTo(button, 'execute', () => {
        const modelElement = editor.model.document.selection.getSelectedElement();
        const metadataRepository = editor.plugins.get(
          'DrupalMediaMetadataRepository'
        );

        if (isDrupalMedia(modelElement) || isDrupalInlineMedia(modelElement)) {
          metadataRepository
            .getMetadata(modelElement)
            .then((metadata) => {
              navigator.clipboard.writeText(metadata.imageSourceMetadata.filepath);
              alert("Copied the file path: " + metadata.imageSourceMetadata.filepath);
            })
            .catch((e) => {
              // There isn't any UI indication for errors because this should be
              // always called after the Drupal Media has been upcast, which would
              // already display an error in the UI.
              // @see module:drupalMedia/mediaimagetextalternative/mediaimagetextalternativeediting~MediaImageTextAlternativeEditing
              console.warn(e.toString());
            });
        }


      });

      return button;
    });
  }
}
