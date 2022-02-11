<?php

namespace Drupal\epa_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\addtocal\Form\AddToCalForm;
use Drupal\date_range_formatter\Plugin\Field\FieldFormatter\DateRangeFormatterRangeFormatter;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeFieldItemList;

/**
 * Plugin implementation of the 'HierarchicalFacetFormatter' formatter.
 *
 * @FieldFormatter(
 *   id = "epa_core_add_to_calendar_formatter",
 *   label = @Translation("Add to Calendar Formatter"),
 *   description = @Translation("Provides Add to Calendar button."),
 *   field_types = {
 *     "daterange"
 *   }
 * )
 */
class AddToCalendarFormatter extends DateRangeFormatterRangeFormatter {

  /**
   * {@inheritdoc}
   */
  static public function defaultSettings() {
    return [
        'location' => ['value' => FALSE, 'tokenized' => ''],
        'description' => ['value' => FALSE, 'tokenized' => ''],
        'past_events' => FALSE, // The version of the addtocal module we are using doesn't actually implement this setting correctly.
        'separator' => '-',
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $settings = $this->getSettings();
    $location = $settings['location']['value'] ? $settings['location']['value'] : $this->t("Static Text");
    $description = $settings['description']['value'] ? $settings['description']['value'] : $this->t("Static Text");
    $summary[] = $this->t('Location field: %location', ['%location' => $location]);
    $summary[] = $this->t('Description field: %description', ['%description' => $description]);
    $summary[] = $this->t('Displays an Add to Calendar button');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $settings = $this->getSettings();
    $field = $this->fieldDefinition;
    $location_field_types = ['string', 'text_with_summary', 'address'];
    $description_field_types = ['string', 'text_long', 'text_with_summary', 'string_long'];
    $description_options = $location_options = [FALSE => 'None'];

    $entity_field_list = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions($field->getTargetEntityTypeId(), $field->getTargetBundle());
    foreach ($entity_field_list as $entity_field) {
      // Filter out base fields like nid, uuid, revisions, etc.
      if ($entity_field->getFieldStorageDefinition()->isBaseField() == FALSE) {
        if (in_array($entity_field->get('field_type'), $location_field_types)) {
          $location_options[$entity_field->get('field_name')] = $entity_field->getLabel();
        }
        if (in_array($entity_field->get('field_type'), $description_field_types)) {
          $description_options[$entity_field->get('field_name')] = $entity_field->getLabel();
        }
      }
    }

    $form['location'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Location'),
      '#open' => TRUE,
    ];
    $form['location']['value'] = [
      '#title' => $this->t('Location Field:'),
      '#type' => 'select',
      '#options' => $location_options,
      '#default_value' => isset($settings['location']['value']) ? $settings['location']['value'] : '',
      '#description' => $this->t('A field to use as the location for calendar events.'),
    ];
    $form['location']['tokenized'] = [
      '#title' => $this->t('Tokenized Location Contents:'),
      '#type' => 'textarea',
      '#default_value' => isset($settings['location']['tokenized']) ? $settings['location']['tokenized'] : '',
      '#description' => $this->t('You can insert static text or use tokens (see the token chart below).'),
    ];
    $form['description'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Description'),
      '#open' => TRUE,
    ];
    $form['description']['value'] = [
      '#title' => $this->t('Description Field:'),
      '#type' => 'select',
      '#options' => $description_options,
      '#default_value' => $this->getSetting('description'),
      '#description' => $this->t('A field to use as the description for calendar events. <em>The contents used from this field will be truncated to 1024 characters</em>.'),
    ];
    $form['description']['tokenized'] = [
      '#title' => $this->t('Tokenized Description Contents:'),
      '#type' => 'textarea',
      '#default_value' => isset($settings['description']['tokenized']) ? $settings['description']['tokenized'] : '',
      '#description' => $this->t('You can insert static text or use tokens (see the token chart below).'),
    ];

    return $form;
  }
}
