<?php
namespace Drupal\epa_core\Plugin\Field\FieldFormatter;

use Drupal\addtocal\Form\AddToCalForm;
use Drupal\date_range_formatter\Plugin\Field\FieldFormatter\DateRangeFormatterRangeFormatter;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeFieldItemList;
use Drupal\epa_core\Plugin\Field\FieldFormatter\AddToCalendarFormatter;

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
class DateRangeFormatterSmartDateAddToCal extends AddToCalendarFormatter {

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

        // If the default timezone is used please populate the timezone to site default
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
          $matches = array();
          if (preg_match_all('/\{([a-zA-Z])\}/', $date_str, $matches)) {
            foreach ($matches[1] as $match) {
              $date_str = preg_replace('/\{' . $match . '\}/', \Drupal::service('date.formatter')->format($end_date, 'custom', $match, $timezone), $date_str);
            }
          }
          $elements[$delta] = ['#markup' => '<span class="date-display-range">' . $date_str . '</span>',];

        }
        else {
          $elements[$delta] = ['#markup' => \Drupal::service('date.formatter')->format($start_date, 'custom', t($this->getSetting('one_day')), $timezone)];
        }
      }

      $t = date_default_timezone_get();
      // Adding AddtoCal
      $form = new AddToCalForm($entity, $settings, $delta);
      $form = \Drupal::formBuilder()->getForm($form);

      // Adjusting the timezone
      $now = new \DateTime('', new \DateTimeZone($timezone));
      $now = $now->format('Y-m-d H:i:s');

      $event_start_date = new \DateTime(date('Y-m-d H:i:s', $start_date), new \DateTimeZone(($timezone)));
      $event_start_date = $event_start_date->format('Y-m-d H:i:s');

      // Need to compare the current time & the event date.
      // To display the addtocal or not.
      // @TODO: need to work on getting timezones accounted for
      // When the event was setup for 3pm ASKT
      // $now = current time 11am
      // $event_start_date = 19.
      // So it looks like while comparing them may be a little more difficult since the $now time (- offset) and
      // $event-start_date appears to be (+ offset) to eastern.
      $form['#access'] =  $now < $event_start_date;

      $elements[$delta]['addtocal'] = $form;
    }
    return $elements;
  }
}
