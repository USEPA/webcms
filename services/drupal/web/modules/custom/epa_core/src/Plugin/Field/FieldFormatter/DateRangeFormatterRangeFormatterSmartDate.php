<?php
namespace Drupal\epa_core\Plugin\Field\FieldFormatter;

use Drupal\date_range_formatter\Plugin\Field\FieldFormatter\DateRangeFormatterRangeFormatter;

/**
* Plugin implementation of the 'Custom' formatter for 'daterange' fields.
*
* This formatter renders the data range as plain text, with a fully
* configurable date format using the PHP date syntax and separator.
*
* @FieldFormatter(
*   id = "date_range_without_time_smartdate",
*   label = @Translation("Date range Smart Date (without time)"),
*   field_types = {
*     "smartdate"
*   }
* )
*/
class DateRangeFormatterRangeFormatterSmartDate extends DateRangeFormatterRangeFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements($items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {

      if (!empty($item->value) && !empty($item->end_value)) {
        /** @var \Drupal\Core\Datetime\DrupalDateTime $start_date */
        $start_date = $item->value;
        /** @var \Drupal\Core\Datetime\DrupalDateTime $end_date */
        // $item->timezone;
        $end_date = $item->end_value;
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

          $date_str = \Drupal::service('date.formatter')->format($start_date, 'custom', preg_replace('/\{([a-zA-Z])\}/', '{\\\$1}', t($format)), $item->timezone);
          $matches = array();
          if (preg_match_all('/\{([a-zA-Z])\}/', $date_str, $matches)) {
            foreach ($matches[1] as $match) {
              $date_str = preg_replace('/\{' . $match . '\}/', \Drupal::service('date.formatter')->format($end_date, 'custom', $match, $item->timezone), $date_str);
            }
          }
          $elements[$delta] = ['#markup' => '<span class="date-display-range">' . $date_str . '</span>',];

        }
        else {
          $elements[$delta] = ['#markup' => \Drupal::service('date.formatter')->format($start_date, 'custom', t($this->getSetting('one_day')), $item->timezone)];
        }
      }
    }
    return $elements;
  }
}
