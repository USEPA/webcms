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
    $media = $items->getEntity();

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      $extension = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
      $options = [];
      $options['attributes']['type'] = $file->getMimeType() . '; length=' . $file->getSize();
      $options['attributes']['title'] = $file->getFilename();
      $url = Url::fromUri($file->createFileUrl(FALSE), $options);
      $link = Link::fromTextAndUrl($media->getName() ." ($extension)", $url)->toString();

      $markup = [
        '#type' => 'markup',
        '#markup' => $link,
        '#cache' => [
          'tags' => $file->getCacheTags(),
        ],
      ];
      $elements[$delta] = $markup;
    }
    return $elements;
  }

}
