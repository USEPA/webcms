<?php

namespace Drupal\danse_moderation_notifications\Plugin\DanseRecipientSelection;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\danse\PayloadInterface;
use Drupal\danse\RecipientSelectionBase;

/**
 * Plugin implementation of the DANSE entity recipient selection.
 *
 * @DanseRecipientSelection(
 *   id = "transition_entity",
 *   deriver =
 *   "Drupal\danse_moderation_notifications\Plugin\DanseRecipientSelection\TransitionEntityDeriver"
 * )
 */
class TransitionEntity extends RecipientSelectionBase {

  /**
   * The notification service.
   *
   * @var \Drupal\danse_moderation_notifications\Notification
   */
  protected $notificationService;

  public function getRecipients(PayloadInterface $payload): array {
    $result = [];

    // Each of the TransitionEntity plugins is iterated over, in the Danse PluginBase, but we want to ensure we create notifications for the correct plugin.
    $plugin_id = $this->getPluginId();
    $transition_id = strstr($plugin_id, "transition_entity:");
    $transition_id = str_replace("transition_entity:", "", $transition_id);
    // Load the node revision from the payload.
    $revision = $payload->getEntity();
    $notification_uid = $revision->getRevisionUser()->id();

    /** @var \Drupal\danse_moderation_notifications\NotificationInformation[] $notifications */
    $notifications = \Drupal::service('danse_moderation_notifications.notification_information')
      ->getNotifications($revision);

    // Ensure the transition we're acting on is the same plugin we're on right now.
    if (!empty($notifications) && $transition_id === key($notifications)) {
      /** @var \Drupal\danse_moderation_notifications\Notification $recipients */
      $recipients = \Drupal::service('danse_moderation_notifications.notification')
        ->getNotificationRecipients($revision, $notifications);
      $result = $recipients['to'];
    }

    // Remove $notification_uid from $result.
    // The user who initiated the event, doesn't need to be notified.
    if (in_array($notification_uid, $result)) {
      $key = array_search($notification_uid, $result);
      unset($result[$key]);
      // Re-index the array.
      $result = array_values($result);
    }

    return $result;
  }

}
