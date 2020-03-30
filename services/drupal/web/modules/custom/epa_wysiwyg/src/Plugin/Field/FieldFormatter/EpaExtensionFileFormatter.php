<?php

namespace Drupal\epa_wysiwyg\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Plugin implementation of the 'file_with_extension' formatter.
 *
 * @FieldFormatter(
 *   id = "file_with_extension",
 *   label = @Translation("File with Extension"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class EpaExtensionFileFormatter extends FileFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      $mimetype = $file->getMimeType();
      $extension = explode('/', $mimetype)[1]; // does not work perfectly for every mimetype.
      $options = [];
      $options['attributes']['type'] = $mimetype . '; length=' . $file->getSize();
      $options['attributes']['title'] = $file->getFilename();
      $url = Url::fromUri($file->createFileUrl(FALSE), $options);
      $link = Link::fromTextAndUrl(t($file->label() . '(' . $extension . ')'), $url)->toRenderable();

      $elements[$delta] = $link;
    }

    return $elements;
  }

}
