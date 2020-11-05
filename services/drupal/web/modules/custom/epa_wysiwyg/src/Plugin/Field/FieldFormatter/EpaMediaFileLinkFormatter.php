<?php

namespace Drupal\epa_wysiwyg\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Plugin implementation of the 'epa_media_file_link_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "epa_media_file_link_formatter",
 *   label = @Translation("Link file using media name"),
 *   field_types = {
 *     "file",
 *     "image"
 *   }
 * )
 */
class EpaMediaFileLinkFormatter extends FileFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $media = $items->getEntity();

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      $extension = pathinfo($file->getFilename(), PATHINFO_EXTENSION);

      $content = [
        '#theme' => 'epa_file_link',
        '#file' => $file,
        '#link_text' => $media->getName() . ($this->getSetting('show_extension') ? " ($extension)": NULL),
        '#cache' => [
          'tags' => $file->getCacheTags(),
        ],
      ];

      // Pass field item attributes to the theme function.
      if (isset($item->_attributes)) {
        $content += ['#attributes' => []];
        $content['#attributes'] += $item->_attributes;
        // Unset field item attributes since they have been included in the
        // formatter output and should not be rendered in the field template.
        unset($item->_attributes);
      }
      $elements[$delta] = $content;
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();

    $settings['show_extension'] = TRUE;

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['show_extension'] = [
      '#title' => $this->t('Show file extension'),
      '#description' => $this->t('Adds the file extension in parentheses after the media title.'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('show_extension'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    if ($this->getSetting('show_extension')) {
      $summary[] = $this->t('Show file extension');
    }

    return $summary;
  }


}
