<?php

namespace Drupal\epa_inline_media\Plugin\CKEditorPlugin;

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
    return $this->moduleExtensionList->getPath('epa_inline_media') . '/js/plugins/drupalinlinemedia/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [
      'DrupalInlineMedia' => [
        'label' => $this->t('Insert inline from Media Library'),
        'image' => $this->moduleExtensionList->getPath('epa_inline_media') . '/js/plugins/drupalinlinemedia/icons/drupalinlinemedia.png',
      ],
    ];
  }

}
