<?php

namespace Drupal\epa_core\Plugin\Field\FieldFormatter;

use Drupal\addtocal\Form\AddToCalForm;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\date_range_formatter\Plugin\Field\FieldFormatter\DateRangeFormatterRangeFormatter;

/**
 * Plugin implementation of the 'Custom' formatter for 'daterange' fields.
 *
 * This formatter renders the data range as plain text, with a fully
 * configurable date format using the PHP date syntax and separator.
 *
 * @FieldFormatter(
 *   id = "date_range_without_time_smartdate",
 *   label = @Translation("Date range Smart Date includes AddtoCal"),
 *   field_types = {
 *     "smartdate"
 *   }
 * )
 */
class DateRangeFormatterSmartDateAddToCal extends DateRangeFormatterRangeFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'location' => ['value' => FALSE, 'tokenized' => ''],
      'description' => ['value' => FALSE, 'tokenized' => ''],
    // The version of the addtocal module we are using doesn't actually implement this setting correctly.
      'past_events' => FALSE,
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
      '#default_value' => $settings['location']['value'] ?? '',
      '#description' => $this->t('A field to use as the location for calendar events.'),
    ];
    $form['location']['tokenized'] = [
      '#title' => $this->t('Tokenized Location Contents:'),
      '#type' => 'textarea',
      '#default_value' => $settings['location']['tokenized'] ?? '',
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
      '#default_value' => $settings['description']['tokenized'] ?? '',
      '#description' => $this->t('You can insert static text or use tokens (see the token chart below).'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements($items, $langcode) {
    $elements = [];

    $entity = $items->getEntity();
    $settings = $this->getSettings();
    $field = $this->fieldDefinition;
    $field_name = $field->get('field_name');
    $settings['field_name'] = $field_name;

    foreach ($items as $delta => $item) {

      if (!empty($item->value) && !empty($item->end_value)) {
        $start_date = $item->value;
        $end_date = $item->end_value;

        // If the default timezone is used please populate the timezone to site default.
        $timezone = (!empty($item->timezone) ? $item->timezone : 'America/New_York');

        if ($start_date !== $end_date) {
          $format = $this->getSetting('several_years');
          if (date('Y', $start_date) === date('Y', $end_date)) {
            $format = $this->getSetting('several_months');
          }
          if (date('m.Y', $start_date) === date('m.Y', $end_date)) {
            $format = $this->getSetting('one_month');
          }
          if (date('d.m.Y', $start_date) === date('d.m.Y', $end_date)) {
            $format = $this->getSetting('one_day');
          }

          $date_str = \Drupal::service('date.formatter')->format($start_date, 'custom', preg_replace('/\{([a-zA-Z])\}/', '{\\\$1}', t($format)), $timezone);
          $matches = [];
          if (preg_match_all('/\{([a-zA-Z])\}/', $date_str, $matches)) {
            foreach ($matches[1] as $match) {
              $date_str = preg_replace('/\{' . $match . '\}/', \Drupal::service('date.formatter')->format($end_date, 'custom', $match, $timezone), $date_str);
            }
          }
          $elements[$delta] = ['#markup' => '<span class="date-display-range">' . $date_str . '</span>'];

        }
        else {
          $elements[$delta] = ['#markup' => \Drupal::service('date.formatter')->format($start_date, 'custom', t($this->getSetting('one_day')), $timezone)];
        }
      }

      // Adding AddtoCal.
      $form = new AddToCalForm($entity, $settings, $delta);
      $form = \Drupal::formBuilder()->getForm($form);

      // Creating the unix timestamp to be datetime format
      // to account for timezone for comparison.
      $event = new DrupalDateTime();
      $event = $event->createFromTimestamp($start_date, new \DateTimeZone($timezone));
      $event = $event->getPhpDateTime();

      // Creating datetime object of the current time
      // to account for timezone for comparison.
      $now = new DrupalDateTime('', new \DateTimeZone($timezone));
      $now = $now->getPhpDateTime();

      // Need to verify the date object to see if event is in the past.
      // if it is in the past DO NOT show AddToCal.
      $form['#access'] = $now < $event;

      $elements[$delta]['addtocal'] = $form;
    }
    return $elements;
  }

}
