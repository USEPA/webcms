import { Command } from 'ckeditor5/src/core';
import { isDrupalMedia } from "../../../../../../../core/modules/ckeditor5/js/ckeditor5_plugins/drupalMedia/src/utils";
import { isDrupalInlineMedia } from "../../../../../../contrib/media_inline_embed/js/ckeditor5_plugins/mediaInlineEmbed/src/utils";

export default class EditDrupalMediaCommand extends Command {
  execute() {
    const modelElement = this.editor.model.document.selection.getSelectedElement();
    const metadataRepository = this.editor.plugins.get(
      'DrupalMediaMetadataRepository'
    );

    if (isDrupalMedia(modelElement) || isDrupalInlineMedia(modelElement)) {
      metadataRepository
        .getMetadata(modelElement)
        .then((metadata) => {
          window.open(metadata.edit_url, '_blank');
        })
        .catch((e) => {
          // There isn't any UI indication for errors because this should be
          // always called after the Drupal Media has been upcast, which would
          // already display an error in the UI.
          // @see module:drupalMedia/mediaimagetextalternative/mediaimagetextalternativeediting~MediaImageTextAlternativeEditing
          console.warn(e.toString());
        });
    }
  }
}
