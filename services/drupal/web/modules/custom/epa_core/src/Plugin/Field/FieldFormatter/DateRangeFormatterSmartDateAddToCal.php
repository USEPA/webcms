<?php
namespace Drupal\epa_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\date_range_formatter\Plugin\Field\FieldFormatter\DateRangeFormatterRangeFormatter;
use Spatie\CalendarLinks\Generators\Google;
use Spatie\CalendarLinks\Generators\Ics;
use Spatie\CalendarLinks\Generators\WebOffice;
use Spatie\CalendarLinks\Generators\WebOutlook;
use Spatie\CalendarLinks\Generators\Yahoo;
use Spatie\CalendarLinks\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* Plugin implementation of a formatter for 'daterange' fields that works with
* smartdate fields and displays an AddtoCal widget.
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
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;


  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;


  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->moduleHandler = $container->get('module_handler');
    $instance->token = $container->get('token');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  static public function defaultSettings() {
    return [
        'event_title' => '',
        'location' => '',
        'description' => '',
        'past_events' => FALSE,
        'hide_add_to_cal' => FALSE,
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $field = $this->fieldDefinition;

    $title = $this->getSetting('event_title');
    $summary[] = $this->t('Event title: %title', ['%title' => $title ?: $this->t('Entity label')]);

    $location = $this->getSetting('location');
    if ($location) {
      $summary[] = $this->t('Event location: %location', ['%location' => $location]);
    }

    $description = $this->getSetting('description');
    if ($description) {
      $summary[] = $this->t('Event description: %description', ['%description' => $description]);
    }

    $hide_add_to_cal = $this->getSetting('hide_add_to_cal') ? 'Yes' : 'No';
    $summary[] = $this->t('Hide the AddtoCal widget: %hide_add_to_cal', ['%hide_add_to_cal' => $hide_add_to_cal]);

    $past_events = $this->getSetting('past_events') ? 'Yes' : 'No';
    $summary[] = $this->t('Show the widget for past events: %past_events', ['%past_events' => $past_events]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $field = $this->fieldDefinition;

    $form['event_title'] = [
      '#title' => $this->t('Event title'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('event_title'),
      '#description' => $this->t('Optional - if left empty, the entity label will be used. You can use static text or tokens.'),
    ];

    $form['location'] = [
      '#title' => $this->t('Event location'),
      '#type' => 'textarea',
      '#default_value' => $this->getSetting('location'),
      '#description' => $this->t('Optional. You can use static text or tokens.'),
    ];

    $form['description'] = [
      '#title' => $this->t('Event description'),
      '#type' => 'textarea',
      '#default_value' => $this->getSetting('description'),
      '#description' => $this->t('Optional. You can use static text or tokens.'),
    ];

    $form['hide_add_to_cal'] = [
      '#title' => $this->t('Hide Add to Cal widget?'),
      '#type' => 'checkbox',
      '#description' => $this->t('By default if unchecked the Addtocal widget is shown. If checked this hides the Addtocal widget.'),
      '#default_value' => $this->getSetting('hide_add_to_cal'),
    ];

    $form['past_events'] = [
      '#title' => $this->t('Show Add to Cal widget for past events?'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('past_events'),
    ];

    $form['token_tree_link'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => [
        $field->getTargetEntityTypeId(),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements($items, $langcode) {
    $elements = [];

    $entity = $items->getEntity();
    $field = $this->fieldDefinition;
    $elements['#cache']['contexts'][] = 'timezone';

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
            if ($item->duration == '1439') {
              $format = $this->getSetting('single_all_day');
            }
            else {
              $format = $this->getSetting('one_day');
            }
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

        // Addtocal code
        if (filter_var($this->getSetting('hide_add_to_cal'), FILTER_VALIDATE_BOOLEAN) === TRUE) {
          continue;
        }

        $elements['#attached']['library'][] = 'addtocal/addtocal';
        $start_date = DrupalDateTime::createFromTimestamp($item->value, $timezone);
        $end_date = DrupalDateTime::createFromTimestamp($item->end_value, $timezone);

        $is_all_day = in_array($this->getFieldSetting('datetime_type'), ['date', 'allday']);

        if ($is_all_day) {
          // A date without time will pick up the current time, set to midnight.
          $start_date->modify('midnight');
          $end_date->modify('midnight');
        }
        $is_now_before_start_date = new \DateTime('now') < $start_date->getPhpDateTime();

        $token_data = [
          $field->getTargetEntityTypeId() => $entity,
        ];

        $title = $this->token->replace($this->getSetting('event_title'), $token_data, ['clear' => TRUE]) ?: $entity->label();

        if ($is_all_day) {
          $date_diff = $end_date->diff($start_date);
          // Google calendar all day events count days a little differently:
          $diff_days = 1 + $date_diff->days;
          $link = Link::createAllDay($title, $start_date->getPhpDateTime(), $diff_days);
        }
        else {
          $link = Link::create($title, $start_date->getPhpDateTime(), $end_date->getPhpDateTime());
        }

        $link->address($this->token->replace($this->getSetting('location'), $token_data, ['clear' => TRUE]));
        $link->description($this->token->replace($this->getSetting('description'), $token_data, ['clear' => TRUE]));

        $element_id = 'addtocal-' . $entity->bundle() . '-' . $field->getName() . '-' . $entity->id() . '--' . $delta;

        $addtocal_access = $this->getSetting('past_events') ? TRUE : $is_now_before_start_date;

        $links = [
          '#theme' => 'addtocal_links',
          '#addtocal_link' => $link,
          '#id' => $element_id,
          '#attributes' => [],
          '#button_text' => $this->t('Add to Calendar'),
          '#button_attributes' => [
            'aria-label' => $this->t('Open Add to Calendar menu'),
          ],
          '#menu_attributes' => [],
          '#items' => [
            'google' => [
              'title' => $this->t('Google'),
              'aria-label' => $this->t('Add to Google Calendar'),
              'generator' => new Google(),
            ],
            'yahoo' => [
              'title' => $this->t('Yahoo!'),
              'aria-label' => $this->t('Add to Yahoo Calendar'),
              'generator' => new Yahoo(),
            ],
            'web_outlook' => [
              'title' => $this->t('Outlook.com'),
              'aria-label' => $this->t('Add to Outlook.com Calendar'),
              'generator' => new WebOutlook(),
            ],
            'web_office' => [
              'title' => $this->t('Office.com'),
              'aria-label' => $this->t('Add to Office.com Calendar'),
              'generator' => new WebOffice(),
            ],
            'ics' => [
              'title' => $this->t('iCal / MS Outlook'),
              'aria-label' => $this->t('Add to iCal / MS Outlook'),
              'generator' => new Ics(),
            ],
          ],
          '#access' => $addtocal_access,
        ];

        $context = [
          'items' => $items,
          'langcode' => $langcode,
          'delta' => $delta,
        ];
        $this->moduleHandler->alter('addtocal_links', $links, $context);

        $elements[$delta]['addtocal'] = $links;
      }
    }
    return $elements;
  }
}
