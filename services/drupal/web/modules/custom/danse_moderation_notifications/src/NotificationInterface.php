<?php

namespace Drupal\danse_moderation_notifications;

use Drupal\Core\Entity\EntityInterface;

/**
 * Interface for notification service.
 */
interface NotificationInterface {

  /**
   * Processes a given entity in transition.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being transitioned from one state to another.
   */
  public function processEntity(EntityInterface $entity);

  /**
   * Gets recipients for a given entity and set of notifications.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity we may be moderating.
   * @param \Drupal\danse_moderation_notifications\DanseModerationNotificationsInterface[] $notifications
   *   List of content moderation notification entities.
   *
   * @return bool
   *   TRUE if this entity is moderated, FALSE otherwise.
   */
  public function getNotificationRecipients(EntityInterface $entity, array $notifications);

}
