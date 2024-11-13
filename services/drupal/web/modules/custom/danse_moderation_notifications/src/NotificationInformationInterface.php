<?php

namespace Drupal\danse_moderation_notifications;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Interface for notification_information service.
 */
interface NotificationInformationInterface {

  /**
   * Determines if an entity is moderated.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity we may be moderating.
   *
   * @return bool
   *   TRUE if this entity is moderated, FALSE otherwise.
   */
  public function isModeratedEntity(EntityInterface $entity);

  /**
   * Checks for the workflow object of the moderated entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity we may be moderating.
   *
   * @return \Drupal\workflows\WorkflowInterface|bool
   *   The workflow object if the entity is moderated, FALSE otherwise.
   */
  public function getWorkflow(ContentEntityInterface $entity);

  /**
   * Set for the workflow object of the moderated entity.
   *
   * @param \Drupal\workflows\WorkflowInterface $workflow
   *    The workflow object if the entity is moderated, FALSE otherwise.
   */
  public function setWorkflow(WorkflowInterface $workflow);

  /**
   * Checks for the current transition of the moderated entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity we may be moderating.
   *
   * @return \Drupal\workflows\TransitionInterface|bool
   *   The transition object if the entity is moderated FALSE otherwise.
   */
  public function getTransition(ContentEntityInterface $entity);

  /**
   * Gets the from/previous state of the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The moderated entity.
   *
   * @return \Drupal\workflows\StateInterface
   *   The current state of the entity.
   */
  public function getPreviousState(ContentEntityInterface $entity);

  /**
   * Gets the list of notification based on the current transition.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity we may be moderating.
   *
   * @return \Drupal\danse_moderation_notifications\DanseModerationNotificationsInterface[]
   *   An array containing the notifications list.
   */
  public function getNotifications(EntityInterface $entity);

}
