<?php

namespace Drupal\epa_workflow;

/**
 * Provides services for notifications for the EPA workflow module.
 */
class EpaNotificationService {

  /**
   * Get count of notifications for the current user.
   *
   * @return int
   *   The count of notifications.
   */
  public static function getNotificationsCount() {
    $count = 0;

    $query = \Drupal::database()->select('danse_notification', 'dn');
    $query->addExpression('COUNT(dn.id)', 'count');
    $query->condition('dn.uid', \Drupal::currentUser()->id());
    $query->condition('dn.seen', FALSE);
    $count = $query->execute()->fetchField();

    return $count;
  }

}
