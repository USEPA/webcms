<?php

namespace Drupal\danse_moderation_notifications;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Service for notification related questions about the moderated entity.
 */
class NotificationInformation implements NotificationInformationInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * General service for moderation-related questions about Entity API.
   *
   * @var \Drupal\content_moderation\ModerationInformation
   */
  protected $moderationInformation;

  /**
   * The workflow object we're interacting with.
   *
   * @var \Drupal\workflows\Entity\Workflow
   */
  protected $workflow;

  /**
   * Creates a new NotificationInformation instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_information
   *   The bundle information service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModerationInformationInterface $moderation_information) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moderationInformation = $moderation_information;
  }

  /**
   * {@inheritdoc}
   */
  public function isModeratedEntity(EntityInterface $entity) {
    return $this->moderationInformation->isModeratedEntity($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviousState(ContentEntityInterface $entity) {
    $workflow = $this->getWorkflow($entity);
    $revision_ids = \Drupal::entityTypeManager()->getStorage('node')->revisionIds($entity);
    // Since revisionIds() returns ids in asc order (newest last), we need to
    // reverse the array and grab the 2nd revision id.
    $revision_ids = array_slice(array_reverse(array_values($revision_ids)),1,1);
    $previous_revision_id = reset($revision_ids);

    /** @var \Drupal\node\Entity\Node $last_revision */
    $last_revision = \Drupal::entityTypeManager()->getStorage('node')->loadRevision($previous_revision_id);
    $previous_state = $last_revision->get('moderation_state')->getString() ?? FALSE;

    if (!$previous_state) {
      $previous_state = $workflow->getTypePlugin()->getInitialState($entity)->id();
    }

    return $previous_state;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflow(ContentEntityInterface $entity) {
    if (!$this->workflow) {
      $this->setWorkflow($this->moderationInformation->getWorkflowForEntity($entity));
    }
   return $this->workflow;
  }

  /**
   * {@inheritdoc}
   */
  public function setWorkflow(WorkflowInterface $workflow): void {
    $this->workflow = $workflow;
  }

  /**
   * {@inheritdoc}
   */
  public function getTransition(ContentEntityInterface $entity) {
    $transition = FALSE;
    if (($workflow = $this->getWorkflow($entity))) {
      $current_state = $entity->moderation_state->value;
      $previous_state = $this->getPreviousState($entity);

      try {
        $transition = $workflow->getTypePlugin()->getTransitionFromStateToState($previous_state, $current_state);
      }
      catch (\InvalidArgumentException $e) {
        // There is no available transition. Fall through to return FALSE.
      }
    }

    return $transition;
  }

  /**
   * {@inheritdoc}
   */
  public function getNotifications(EntityInterface $entity) {
    $notifications = [];

    if ($this->isModeratedEntity($entity)) {
      $workflow = $this->getWorkflow($entity);
      if ($transition = $this->getTransition($entity)) {
        // Find out if we have a config entity that contains this transition.
        $query = $this->entityTypeManager->getStorage('danse_moderation_notifications')
          ->getQuery()
          ->condition('workflow', $workflow->id())
          ->condition('status', 1)
          ->condition('transitions.' . $transition->id(), $transition->id());

        $notification_ids = $query->execute();

        $notifications = $this->entityTypeManager
          ->getStorage('danse_moderation_notifications')
          ->loadMultiple($notification_ids);
      }
    }

    return $notifications;
  }

  /**
   * {@inheritdoc}
   */
  public function getLatestRevision($entity_type_id, $entity_id) {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    if ($storage instanceof RevisionableStorageInterface
      && $revision_id = $storage->getLatestRevisionId($entity_id)) {
      return $storage->loadRevision($revision_id);
    }
  }

}
