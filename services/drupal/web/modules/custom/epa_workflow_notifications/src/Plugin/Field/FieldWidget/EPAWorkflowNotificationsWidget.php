<?php

namespace Drupal\epa_workflow_notifications\Plugin\Field\FieldWidget;

use DateTimeZone;
use Drupal\content_moderation_notifications\NotificationInformationInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\epa_workflow_notifications\Plugin\Field\FieldType\EPAWorkflowNotificationsItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'epa_workflow_notifications' widget.
 *
 * @FieldWidget(
 *   id = "epa_workflow_notifications",
 *   label = @Translation("EPA Workflow Notifications"),
 *   field_types = {
 *     "epa_workflow_notifications"
 *   }
 * )
 */
class EPAWorkflowNotificationsWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The notification information service.
   *
   * @var \Drupal\epa_workflow_notifications\NotificationInformation
   */
  protected $notificationInformation;

  /**
   * Constructs a WidgetBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\content_moderation_notifications\NotificationInformationInterface $notification_information
   *   The notification information service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, NotificationInformationInterface $notification_information) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->notificationInformation = $notification_information;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('content_moderation_notifications.notification_information')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $entity = $items->getEntity();

    $options = $this->notificationInformation->getNotificationsList($entity);

    // Add an empty option if the widget needs one.
    if ($empty_label = $this->getEmptyLabel()) {
      $options = [
        '_none' => $empty_label,
      ] + $options;
    }

    if ($form_state->getBuildInfo()['base_form_id'] !== 'field_config_form') {
      $element['notification_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Notification type'),
        '#description' => $this->t('Type of notification to schedule.'),
        '#options' => $options,
        '#default_value' => 'draft',
        '#weight' => '0',
        '#required' => $element['#required'],
      ];
    }

    $element['value'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Scheduled date'),
      '#description' => $this->t('The datetime to send the notification.'),
      '#weight' => '0',
      '#default_value' => NULL,
      '#date_increment' => 1,
      '#date_timezone' => drupal_get_user_timezone(),
      '#required' => $element['#required'],
    ];

    if ($items[$delta]->notification_type) {
      $notification = $items[$delta]->notification_type;
      $element['notification_type']['#default_value'] = $notification;
    }

    if ($items[$delta]->date) {
      $date = $items[$delta]->date;
      $date->setTimezone(new \DateTimeZone($element['value']['#date_timezone']));
      $element['value']['#default_value'] = $this->createDefaultValue($date, $element['value']['#date_timezone']);
    }

    if (isset($form['advanced'])) {
      $element += [
        '#type' => 'details',
        '#group' => 'advanced',
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    foreach ($values as &$item) {
      if (!empty($item['value']) && $item['value'] instanceof DrupalDateTime) {
        $date = $item['value'];
        $format = EPAWorkflowNotificationsItem::DATETIME_STORAGE_FORMAT;
        // Adjust the date for storage.
        $date->setTimezone(new DateTimeZone(EPAWorkflowNotificationsItem::STORAGE_TIMEZONE));
        $item['value'] = $date->format($format);
      }
    }

    return $values;
  }

  /**
   * Creates default datetime object.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   The datetime object.
   * @param string $timezone
   *   Timezone used to set date.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   Datetime object using timezone.
   */
  private function createDefaultValue(DrupalDateTime $date, string $timezone): DrupalDateTime {
    $date->setTimezone(new \DateTimeZone($timezone));
    return $date;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmptyLabel() {
    if ($this->multiple) {

      // Multiple select: add a 'none' option for non-required fields.
      if (!$this->required) {
        return $this->t('- None -');
      }
    }
    else {

      // Single select: add a 'none' option for non-required fields,
      // and a 'select a value' option for required fields that do not come
      // with a value selected.
      if (!$this->required) {
        return $this->t('- None -');
      }
      if (!$this->has_value) {
        return $this->t('- Select a value -');
      }
    }
  }

}
