<?php

namespace Drupal\epa_workflow;

use Drupal\content_moderation_notifications\Notification;
use Drupal\content_moderation_notifications\NotificationInformationInterface;
use Drupal\content_moderation_notifications\NotificationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\token\TokenEntityMapperInterface;

/**
 * Extend Content Moderation Notifications service.
 */
class EPANotification extends Notification {

  /**
   * Original service object.
   *
   * @var \Drupal\content_moderation_notifications\NotificationInterface
   */
  protected $notificationService;

  /**
   * Creates a new ModerationInformation instance.
   *
   * @param \Drupal\content_moderation_notifications\NotificationInterface $notification
   *   The notification service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\content_moderation_notifications\NotificationInformationInterface $notification_information
   *   The notification information service.
   * @param \Drupal\token\TokenEntityMapperInterface $token_entity_mappper
   *   The token entity mapper service.
   */
  public function __construct(NotificationInterface $notification, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, MailManagerInterface $mail_manager, ModuleHandlerInterface $module_handler, NotificationInformationInterface $notification_information, TokenEntityMapperInterface $token_entity_mappper = NULL) {
    $this->notificationService = $notification;
    parent::__construct($current_user, $entity_type_manager, $mail_manager, $module_handler, $notification_information, $token_entity_mappper);
  }

  /**
   * {@inheritdoc}
   */
  public function processEntity(EntityInterface $entity) {
    // Skip processing entity if is syncing to avoid double notifications.
    if ($entity instanceof ContentEntityInterface && $entity->isSyncing()) {
      return;
    }
    parent::processEntity($entity);
  }

}
