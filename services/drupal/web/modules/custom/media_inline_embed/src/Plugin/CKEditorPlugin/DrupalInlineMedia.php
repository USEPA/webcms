<?php

namespace Drupal\media_inline_embed\Plugin\CKEditorPlugin;

use Drupal\Core\Url;
use Drupal\editor\Entity\Editor;
use Drupal\media_library\MediaLibraryState;
use Drupal\media_library\MediaLibraryUiBuilder;
use Drupal\media_library\Plugin\CKEditorPlugin\DrupalMediaLibrary;

/**
 * Defines the "drupalinlinemedia" plugin.
 *
 * @CKEditorPlugin(
 *   id = "drupalinlinemedia",
 *   label = @Translation("Embed media from the Media Library inline"),
 * )
 */
class DrupalInlineMedia extends DrupalMediaLibrary {

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return $this->moduleExtensionList->getPath('media_inline_embed') . '/js/plugins/drupalinlinemedia/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    // If the editor has not been saved yet, we may not be able to create a
    // coherent MediaLibraryState object, which is needed in order to generate
    // the required configuration. But, if we're creating a new editor, we don't
    // need to do that anyway, so just return an empty array.
    if ($editor->isNew()) {
      return [];
    }

    $media_type_ids = $this->mediaTypeStorage->getQuery()->execute();
    if ($editor->hasAssociatedFilterFormat()) {
      if ($media_embed_filter = $editor->getFilterFormat()->filters()->get('media_inline_embed')) {
        // Optionally limit the allowed media types based on the MediaEmbed
        // setting. If the setting is empty, do not limit the options.
        if (!empty($media_embed_filter->settings['allowed_media_types'])) {
          $media_type_ids = array_intersect_key($media_type_ids, $media_embed_filter->settings['allowed_media_types']);
        }
      }
    }

    if (in_array('image', $media_type_ids, TRUE)) {
      // Due to a bug where the active item styling and the focus styling
      // create the visual appearance of two active items, we'll move
      // the 'image' media type to first position, so that the focused item and
      // the active item are the same.
      // This workaround can be removed once this issue is fixed:
      // @see https://www.drupal.org/project/drupal/issues/3073799
      array_unshift($media_type_ids, 'image');
      $media_type_ids = array_unique($media_type_ids);
    }

    $state = MediaLibraryState::create(
      'media_library.opener.editor',
      $media_type_ids,
      reset($media_type_ids),
      1,
      ['filter_format_id' => $editor->getFilterFormat()->id()]
    );

    return [
      'DrupalInlineMediaLibrary_url' => Url::fromRoute('media_library.ui')
        ->setOption('query', $state->all())
        ->toString(TRUE)
        ->getGeneratedUrl(),
      'DrupalInlineMediaLibrary_dialogOptions' => MediaLibraryUiBuilder::dialogOptions(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [
      'DrupalInlineMedia' => [
        'label' => $this->t('Insert inline from Media Library'),
        'image' => $this->moduleExtensionList->getPath('media_inline_embed') . '/js/plugins/drupalinlinemedia/icons/drupalinlinemedia.png',
      ],
    ];
  }

}
