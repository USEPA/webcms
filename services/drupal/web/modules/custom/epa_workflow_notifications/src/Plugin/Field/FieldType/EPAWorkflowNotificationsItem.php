<?php

namespace Drupal\epa_workflow_notifications\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Plugin implementation of the 'epa_workflow_notifications_type' field type.
 *
 * @FieldType(
 *   id = "epa_workflow_notifications",
 *   label = @Translation("EPA Workflow Notifications"),
 *   description = @Translation("EPA Notifications"),
 *   default_widget = "epa_workflow_notifications",
 *   default_formatter = "epa_workflow_notifications_formatter"
 * )
 */
class EPAWorkflowNotificationsItem extends FieldItemBase implements DateTimeItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {

    $properties['value'] = DataDefinition::create('datetime_iso8601')
      ->setLabel(t('Date value'))
      ->setRequired(TRUE);

    $properties['date'] = DataDefinition::create('any')
      ->setLabel(t('Computed date'))
      ->setDescription(t('The computed DateTime object.'))
      ->setComputed(TRUE)
      ->setClass('\Drupal\datetime\DateTimeComputed')
      ->setSetting('date source', 'value');

    $properties['notification_type'] = DataDefinition::create('string')
      ->setLabel(t('Notification type'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE) {
    // Enforce that the computed date is recalculated.
    if ($property_name === 'value') {
      $this->date = NULL;
    }
    parent::onChange($property_name, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    $schema =
      [
        'columns' => [
          'notification_type' => [
            'type' => 'varchar',
            'length' => 32,
          ],
          'value' => [
            'description' => 'The date value.',
            'type' => 'varchar',
            'length' => 20,
          ],
        ],
        'indexes' => [
          'value' => ['value'],
        ],
      ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $has_notification_type = empty($this->get('notification_type')->getValue());
    $has_value = empty($this->get('value')->getValue());

    return $has_notification_type || $has_value;
  }

}
