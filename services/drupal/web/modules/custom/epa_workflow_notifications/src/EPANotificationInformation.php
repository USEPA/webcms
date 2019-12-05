<?php

namespace Drupal\epa_workflow_notifications;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation_notifications\NotificationInformation;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Extend NotificationInformation.
 */
class EPANotificationInformation extends NotificationInformation {

  /**
   * Original service object.
   *
   * @var \Drupal\content_moderation_notifications\NotificationInformation
   */
  protected $notificationInformation;

  /**
   * Creates a new NotificationInformation instance.
   *
   * @param \Drupal\content_moderation_notifications\NotificationInformation $notification_information
   *   The original notification information service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_information
   *   The bundle information service.
   */
  public function __construct(NotificationInformation $notification_information, EntityTypeManagerInterface $entity_type_manager, ModerationInformationInterface $moderation_information) {
    $this->notificationInformation = $notification_information;
    parent::__construct($entity_type_manager, $moderation_information);
  }

  /**
   * {@inheritdoc}
   */
  public function getNotificationsList(EntityInterface $entity) {
    $notifications_list = [];

    if ($this->isModeratedEntity($entity)) {
      $workflow = $this->getWorkflow($entity);
      // Find all enabled notifications for this workflow.
      $query = $this->entityTypeManager->getStorage('content_moderation_notification')
        ->getQuery()
        ->condition('workflow', $workflow->id())
        ->condition('status', 1);

      $notification_ids = $query->execute()->fetchCol();

      $notifications = $this->entityTypeManager->getStorage('content_moderation_notification')->loadMultiple($notification_ids);

      foreach ($notifications as $notification) {
        $notifications_list[$notification->id()] = $notification->label();
      }
    }

    return $notifications_list;
  }

}
