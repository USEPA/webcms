<?php

namespace Drupal\epa_media\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\image\ImageStyleStorageInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\media\Plugin\Field\FieldFormatter\MediaThumbnailFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the 'epa_media_thumbnail_url' formatter.
 *
 * @FieldFormatter(
 *   id = "epa_media_thumbnail_url",
 *   label = @Translation("Thumbnail as URL"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EpaMediaThumbnailUrlFormatter extends MediaThumbnailFormatter {

  /**
   * The image style entity storage.
   *
   * @var \Drupal\image\ImageStyleStorageInterface
   */
  protected $imageStyleStorage;

  /**
   * Constructs a MediaThumbnailFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\image\ImageStyleStorageInterface $image_style_storage
   *   The image style entity storage handler.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountInterface $current_user, ImageStyleStorageInterface $image_style_storage, RendererInterface $renderer) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $current_user, $image_style_storage, $renderer);
    $this->imageStyleStorage = $image_style_storage;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    unset($element['image_link']);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $media_items = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($media_items)) {
      return $elements;
    }

    /** @var \Drupal\image\ImageStyleInterface $image_style */
    $image_style = $this->imageStyleStorage->load($this->getSetting('image_style'));
    /** @var \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator */
    $file_url_generator = \Drupal::service('file_url_generator');
    /** @var \Drupal\media\MediaInterface[] $media_items */
    foreach ($media_items as $delta => $media) {
      $image_uri = $media->get('thumbnail')->entity->getFileUri();
      $url = $image_style ? $file_url_generator->transformRelative($image_style->buildUrl($image_uri)) : $file_url_generator->generateString($image_uri);

      // Add cacheability metadata from the image and image style.
      $cacheability = CacheableMetadata::createFromObject($media);
      if ($image_style) {
        $cacheability->addCacheableDependency(CacheableMetadata::createFromObject($image_style));
      }

      $elements[$delta] = ['#markup' => $url];
      $cacheability->applyTo($elements[$delta]);
    }
    return $elements;
  }

}
