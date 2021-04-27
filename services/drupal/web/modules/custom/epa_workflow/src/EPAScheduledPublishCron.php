<?php

namespace Drupal\epa_workflow;

use DateTime;
use DateTimeZone;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\scheduled_publish\Plugin\Field\FieldType\ScheduledPublish;
use Drupal\scheduled_publish\Service\ScheduledPublishCron;

/**
 * Class EPAScheduledPublishCron copies ScheduledPublishCron.
 *
 * @see Drupal\scheduled_publish\Service\ScheduledPublishCron
 */
class EPAScheduledPublishCron extends ScheduledPublishCron {

  /**
   * Original service object.
   *
   * @var \Drupal\scheduled_publish\Service\ScheduledPublishCron
   */
  protected $scheduledPublishCron;

  /**
   * The entity bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
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
   * The time component.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  private $dateTime;



  /**
   * The constructor.
   *
   * @param \Drupal\scheduled_publish\Service\ScheduledPublishCron $scheduled_publish_cron
   *   The original service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity bundle info service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $date_time
   *   The datetime.
   */
  public function __construct(ScheduledPublishCron $scheduled_publish_cron, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, TimeInterface $date_time, ModerationInformationInterface $moderation_info, LoggerChannelFactoryInterface $logger) {
    $this->scheduledPublishCron = $scheduled_publish_cron;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->dateTime = $date_time;
    $this->moderationInfo = $moderation_info;
    $this->logger = $logger;
  }

  /**
   * Run field updates.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function doUpdate(): void {
    foreach (self::$supportedTypes as $supportedType) {
      $this->doUpdateFor($supportedType);
    }
  }

  /**
   * Run field update for specific entity type.
   *
   * @param $entityType
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  private function doUpdateFor($entityType) {
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entityType);

    foreach ($bundles as $bundleName => $value) {

      $scheduledFields = $this->getScheduledFields($entityType, $bundleName);
      if (\count($scheduledFields) > 0) {
        foreach ($scheduledFields as $scheduledField) {
          // We need to process both the latest and current revisions since
          // either one could have relevant transitions scheduled.
          foreach (['latestRevision', 'currentRevision'] as $revisionLimiter) {
            $query = $this->entityTypeManager->getStorage($entityType)
              ->getQuery('AND');
            $query->condition($entityType === 'media' ? 'bundle' : 'type', $bundleName);
            $query->condition($scheduledField, NULL, 'IS NOT NULL');
            $query->accessCheck(FALSE);
            $query->$revisionLimiter();
            $entities = $query->execute();
            foreach ($entities as $entityRevision => $entityId) {
              $entity = $this->entityTypeManager->getStorage($entityType)
                ->loadRevision($entityRevision);
              $this->updateEntityField($entity, $scheduledField);
            }
          }
        }
      }
    }
  }

  /**
   * Returns scheduled publish fields.
   *
   * @param string $entityTypeName
   * @param string $bundleName
   *
   * @return array
   */
  private function getScheduledFields(string $entityTypeName, string $bundleName): array {
    $scheduledFields = [];
    $fields = $this->entityFieldManager
      ->getFieldDefinitions($entityTypeName, $bundleName);
    foreach ($fields as $fieldName => $field) {
      /** @var FieldConfig $field */
      if (strpos($fieldName, 'field_') !== FALSE) {
        if ($field->getType() === 'scheduled_publish') {
          $scheduledFields[] = $fieldName;
        }
      }
    }

    return $scheduledFields;
  }

  /**
   * Update scheduled publish fields.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   * @param string $scheduledField
   *
   * @throws \Exception
   */
  private function updateEntityField(ContentEntityBase $entity, string $scheduledField): void {
    /** @var FieldItemList $scheduledEntity */
    $scheduledEntity = $entity->get($scheduledField);
    $scheduledValue = $scheduledEntity->getValue();
    if (empty($scheduledValue)) {
      return;
    }
    $currentModerationState = $entity->get('moderation_state')
      ->getValue()[0]['value'];

    foreach ($scheduledValue as $key => $value) {
      if ($currentModerationState === $value['moderation_state'] ||
        $this->getTimestampFromIso8601($value['value']) <= $this->dateTime->getCurrentTime()) {

        unset($scheduledValue[$key]);
        $this->updateEntity($entity, $value['moderation_state'], $scheduledField, $scheduledValue);
      }
    }
  }

  /**
   * Returns timestamp from ISO-8601 datetime.
   *
   * @param string $dateIso8601
   *
   * @return int
   * @throws \Exception
   */
  private function getTimestampFromIso8601(string $dateIso8601): int {
    $datetime = new DateTime($dateIso8601, new DateTimeZone(ScheduledPublish::STORAGE_TIMEZONE));
    $datetime->setTimezone(new \DateTimeZone(date_default_timezone_get()));

    return $datetime->getTimestamp();
  }

  /**
   * Updates entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   * @param string $moderationState
   * @param string $scheduledPublishField
   * @param $scheduledValue
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @todo Use a better key.
   */
  private function updateEntity(ContentEntityBase $entity, string $moderationState, string $scheduledPublishField, $scheduledValue): void {
    $entity->set($scheduledPublishField, $scheduledValue);
    $entity->set('moderation_state', $moderationState);
    $entity->setRevisionCreationTime($this->dateTime->getCurrentTime());
    $entity->setRevisionLogMessage(t('Created by an automated transition based on revision %vid',
      ['%vid' => $entity->getLoadedRevisionId(), '@current_status' => $moderationState]));
    $entity->set('epa_revision_automated', 1);
    $entity->save();
  }

}
