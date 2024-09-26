<?php

namespace Drupal\danse_moderation_notifications;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines an access controller for the danse_moderation_notifications entity.
 *
 * We set this class to be the access controller in
 * DanseModerationNotifications's entity annotation.
 *
 * @see \Drupal\danse_moderation_notifications\Entity\DanseModerationNotifications
 *
 * @ingroup danse_moderation_notifications
 */
class DanseModerationNotificationsAccessController extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // No special access handling. Defer to the entity system which will only
    // allow admin access by default.
    return parent::checkAccess($entity, $operation, $account);
  }

}
