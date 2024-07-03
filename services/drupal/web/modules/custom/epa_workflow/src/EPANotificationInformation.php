<?php

namespace Drupal\epa_workflow;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\danse_moderation_notifications\NotificationInformation;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Extend NotificationInformation.
 */
class EPANotificationInformation extends NotificationInformation {

  /**
   * Original service object.
   *
   * @var \Drupal\danse_moderation_notifications\NotificationInformation
   */
  protected $notificationInformation;

  /**
   * Creates a new NotificationInformation instance.
   *
   * @param \Drupal\danse_moderation_notifications\NotificationInformation $notification_information
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
  public function getNotifications(EntityInterface $entity) {
    $notifications = [];

    if ($this->isModeratedEntity($entity)) {
      $workflow = $this->getWorkflow($entity);
      if ($transition = $this->getTransition($entity)) {

        // Determine what kicked off this transition so we can make better decisions on which notifications to send.
        $transition_process = !empty($entity->epa_revision_automated) && !empty($entity->epa_revision_automated->value) && $entity->epa_revision_automated->value ? 'automatic' : 'manual';
        $workflow_processes = ['any'];

        // We don't have a direct way of knowing why this is being unpublished,
        // but if we know this was kicked off automatically and we know it is
        // unpublishing and the sunset date is earlier than the review date, then
        // we can safely assume it's being sunsetted.
        if ($transition_process == 'automatic' &&
          $entity->hasField('field_review_deadline') &&
          $entity->hasField('field_expiration_date') &&
          !$entity->get('field_expiration_date')->isEmpty() &&
          'unpublish' == $transition->id() &&
          $entity->field_review_deadline->value > $entity->field_expiration_date->value) {
          $workflow_processes[] = 'sunset';
        }
        else {
          $workflow_processes[] = $transition_process;
        }

        // Find out if we have a config entity that contains this transition.
        $query = $this->entityTypeManager->getStorage('danse_moderation_notifications')
          ->getQuery()
          ->accessCheck(FALSE)
          ->condition('workflow', $workflow->id())
          ->condition('status', 1)
          ->condition('transitions.' . $transition->id(), $transition->id())
          ->condition('third_party_settings.epa_workflow.workflow_process', $workflow_processes, 'IN');

        $notification_ids = $query->execute();

        $notifications = $this->entityTypeManager
          ->getStorage('danse_moderation_notifications')
          ->loadMultiple($notification_ids);
      }
    }

    return $notifications;
  }

}
