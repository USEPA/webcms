<?php

namespace Drupal\epa_workflow_notifications;

use DateTime;
use DateTimeZone;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\epa_workflow_notifications\Plugin\Field\FieldType\EPAWorkflowNotificationsItem;

/**
 * Class EPANotificationCron.
 *
 * @todo Need to actually send the notification.
 */
class EPANotificationCron {

  /**
   * Allowed types for scheduled notifications.
   *
   * @var array
   */
  public static $supportedTypes = [
    'node',
    'media',
  ];

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  private $entityTypeBundleInfo;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  private $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  private $entityTypeManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  private $dateTime;

  /**
   * ScheduledPublishCron constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_bundle_info
   *   The entity bundle info service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $date_time
   *   The time service.
   */
  public function __construct(EntityTypeBundleInfoInterface $entity_bundle_info, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, TimeInterface $date_time) {
    $this->entityTypeBundleInfo = $entity_bundle_info;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->dateTime = $date_time;
  }

  /**
   * Send scheduled notifications.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function sendScheduledNotifications() {
    foreach (self::$supportedTypes as $supported_type) {
      $this->sendScheduledNotificationsFor($supported_type);
    }
  }

  /**
   * Send scheduled notifications for specific entity type.
   *
   * @param string $entity_type
   *   Allowed entity_type to update.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  private function sendScheduledNotificationsFor($entity_type) {
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type);

    foreach ($bundles as $bundle_name => $value) {

      $notification_fields = $this->getNotificationFields($entity_type, $bundle_name);
      if (\count($notification_fields) > 0) {
        foreach ($notification_fields as $notification_field) {
          $query = $this->entityTypeManager->getStorage($entity_type)
            ->getQuery('AND');
          $query->condition($entity_type === 'media' ? 'bundle' : 'type', $bundle_name);
          $query->condition($notification_field, NULL, 'IS NOT NULL');
          $query->accessCheck(FALSE);
          $query->latestRevision();
          $entities = $query->execute();
          foreach ($entities as $entityRevision => $entity_id) {
            $entity = $this->entityTypeManager->getStorage($entity_type)
              ->loadRevision($entityRevision);
            $this->updateEntityField($entity, $notification_field);
          }
        }
      }
    }
  }

  /**
   * Returns list of epa workflow notification fields.
   *
   * @param string $entity_type_name
   *   The name of the entity type.
   * @param string $bundle_name
   *   The bundle name.
   *
   * @return array
   *   Returns array of fields of epa_workflow_notifications type.
   */
  private function getNotificationFields($entity_type_name, $bundle_name) {
    $notification_fields = [];
    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type_name, $bundle_name);
    foreach ($fields as $fieldName => $field) {
      /** @var FieldConfig $field */
      if (strpos($fieldName, 'field_') !== FALSE) {
        if ($field->getType() === 'epa_workflow_notifications') {
          $notification_fields[] = $fieldName;
        }
      }
    }

    return $notification_fields;
  }

  /**
   * Process notification fields.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   *   The entity hosting the field.
   * @param string $notification_field
   *   The field name.
   *
   * @throws \Exception
   */
  private function updateEntityField(ContentEntityBase $entity, $notification_field) {
    /** @var FieldItemList $scheduled_notification */
    $scheduled_notification = $entity->get($notification_field);
    $scheduled_value = $scheduled_notification->getValue();
    if (empty($scheduled_value)) {
      return;
    }

    foreach ($scheduled_value as $key => $value) {
      if ($this->getTimestampFromIso8601($value['value']) <= $this->dateTime->getCurrentTime()) {
        unset($scheduled_value[$key]);
        $this->updateEntity($entity, $notification_field, $scheduled_value);
      }
    }
  }

  /**
   * Returns timestamp from ISO-8601 datetime.
   *
   * @param string $date_iso_8601
   *   The date in ISO-8601 format.
   *
   * @return int
   *   Return the timestamp as an interger.
   *
   * @throws \Exception
   */
  private function getTimestampFromIso8601($date_iso_8601) {
    $datetime = new DateTime($date_iso_8601, new DateTimeZone(EPAWorkflowNotificationsItem::STORAGE_TIMEZONE));
    $datetime->setTimezone(new DateTimeZone(drupal_get_user_timezone()));

    return $datetime->getTimestamp();
  }

  /**
   * Updates entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   *   The entity to update.
   * @param string $notification_field
   *   The field name.
   * @param mixed $scheduled_value
   *   The value of the field to be saved.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function updateEntity(ContentEntityBase $entity, $notification_field, $scheduled_value) {
    $entity->set($notification_field, $scheduled_value);
    $entity->save();
  }

}
