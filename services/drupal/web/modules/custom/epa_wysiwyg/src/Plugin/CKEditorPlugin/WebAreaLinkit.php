<?php

namespace Drupal\epa_wysiwyg\Plugin\CKEditorPlugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;

/**
 * Provides the Web Area Linkit CKEditor 5 plugin.
 *
 * @CKEditor5Plugin(
 *   id = "webAreaLinkit",
 *   label = @Translation("Web Area Linkit"),
 *   module = "epa_wysiwyg"
 * )
 */
class WebAreaLinkit extends CKEditor5PluginDefault {

  /**
   * {@inheritdoc}
   */
  public function getDependencies(): array {
    return [
      'linkit',
      'ckeditor5_link',
    ];
  }

}
