<?php

namespace Drupal\danse_moderation_notifications;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\Group;
use Drupal\flag\FlagService;
use Drupal\token\TokenEntityMapperInterface;
use Drupal\user\Entity\User;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\RoleInterface;

/**
 * General service for moderation-related questions about Entity API.
 */
class Notification implements NotificationInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The notification information service.
   *
   * @var \Drupal\danse_moderation_notifications\NotificationInformationInterface
   */
  protected $notificationInformation;

  /**
   * The token entity mapper, if available.
   *
   * @var \Drupal\token\TokenEntityMapperInterface
   */
  protected $tokenEntityMapper;

  /**
   * @var \Drupal\Core\Plugin\Context\ContextProviderInterface
   */
  protected $contextProvider;

  /**
   * The Flag service.
   *
   * @var \Drupal\flag\FlagService
   */
  protected $flagService;

  /**
   * Creates a new ModerationInformation instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\danse_moderation_notifications\NotificationInformationInterface $notification_information
   *   The notification information service.
   * @param \Drupal\token\TokenEntityMapperInterface $token_entity_mappper
   *   The token entity mapper service.
   * @param \Drupal\flag\FlagService $flag_service
   *   The Flag service.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, MailManagerInterface $mail_manager, ModuleHandlerInterface $module_handler, NotificationInformationInterface $notification_information, TokenEntityMapperInterface $token_entity_mappper = NULL, ContextProviderInterface $context_provider = NULL, FlagService $flag_service = NULL) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager = $mail_manager;
    $this->moduleHandler = $module_handler;
    $this->notificationInformation = $notification_information;
    $this->tokenEntityMapper = $token_entity_mappper;
    $this->contextProvider = $context_provider;
    $this->flagService = $flag_service;
  }

  /**
   * {@inheritdoc}
   */
  public function processEntity(EntityInterface $entity) {
    $notifications = $this->notificationInformation->getNotifications($entity);
    if (!empty($notifications)) {
      $this->sendNotification($entity, $notifications);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getNotificationRecipients(EntityInterface $entity, array $notifications) {
    $data['to'] = [];

    /** @var \Drupal\content_moderation_notifications\ContentModerationNotificationInterface $notification */
    foreach ($notifications as $notification) {
      $data['langcode'] = $this->currentUser->getPreferredLangcode();
      $data['notification'] = $notification;
      // Setup the email subject and body content.
      // Add the entity as context to aid in token and Twig replacement.
      if ($this->tokenEntityMapper) {
        $data['params']['context'] = [
          'entity' => $entity,
          'user' => $this->currentUser,
          $this->tokenEntityMapper->getTokenTypeForEntityType($entity->getEntityTypeId()) => $entity,
        ];
      }
      else {
        $data['params']['context'] = [
          'entity' => $entity,
          'user' => $this->currentUser,
          $entity->getEntityTypeId() => $entity,
        ];
      }

      // Get Subject and process any Twig templating.
      $subject = $notification->getSubject();
      $template = [
        '#type' => 'inline_template',
        '#template' => $subject,
        '#context' => $data['params']['context'],
      ];
      $subject = \Drupal::service('renderer')->renderPlain($template);
      // Remove any newlines from Subject.
      $subject = trim(str_replace("\n", ' ', $subject));
      $data['params']['subject'] = $subject;

      // Get Message, process any Twig templating, and apply input filter.
      $message = $notification->getMessage();
      $template = [
        '#type' => 'inline_template',
        '#template' => $message,
        '#context' => $data['params']['context'],
      ];
      $message = \Drupal::service('renderer')->renderPlain($template);
      $data['params']['message'] = check_markup($message, $notification->getMessageFormat());

      // Figure out who the email should be going to.
      // Get Author.
      if ($notification->author and ($entity instanceof EntityOwnerInterface)) {
        if ($entity->getOwner()->isActive()) {
          $data['to'][] = $entity->getOwner()->id();
        }
      }

      if ($notification->revision_author and ($entity instanceof EntityOwnerInterface)) {
        if (!$entity->getOwner()->isAnonymous()) {
          $revisionListIds = $this->entityTypeManager->getStorage($entity->getEntityTypeId())
            ->revisionIds($entity);
          // Sort $revisionListIds current revision to oldest.
          $revisionListIds = array_reverse($revisionListIds);
          if (isset($revisionListIds[1])) {
            // Load the previous revision.
            $revision = $this->entityTypeManager->getStorage('node')
              ->loadRevision($revisionListIds[1]);
          }
          else {
            $revision = $this->entityTypeManager->getStorage('node')
              ->loadRevision($revisionListIds[0]);
          }
          $data['to'][] = $revision->getRevisionUser()->id();
        }
      }

      // Get Roles.
      foreach ($notification->getRoleIds() as $role) {
        /** @var \Drupal\Core\Entity\EntityStorageInterface $user_storage */
        $user_storage = $this->entityTypeManager->getStorage('user');
        if ($role === RoleInterface::AUTHENTICATED_ID) {
          $uids = \Drupal::entityQuery('user')
            ->condition('status', 1)
            ->accessCheck(FALSE)
            ->execute();
          /** @var \Drupal\user\UserInterface[] $role_users */
          $role_users = $user_storage->loadMultiple(array_filter($uids));
        }
        else {
          /** @var \Drupal\user\UserInterface[] $role_users */
          $role_users = $user_storage->loadByProperties(['roles' => $role]);
        }
        foreach ($role_users as $role_user) {
          if ($role_user->isActive()) {
            // Check for access to view the entity.
            if ($entity->access('view', $role_user)) {
              $data['to'][] = $role_user->id();
            }
          }
        }
      }

      // Specific part to use group module functionality.
      // TODO: This needs to be updated to get user ids, not emails.
      if ($this->moduleHandler->moduleExists('group')
        && $notification->isGroupUse()) {
        $group_contents = $this->entityTypeManager
          ->getStorage('group_content')
          ->loadByEntity($entity);

        if ($group_contents) {
          foreach ($group_contents as $group_content) {
            /** @var \Drupal\group\Entity\Group $group */
            $group = $group_content->getGroup();
            $this->setGroupData($notification, $entity, $group, $data);
          }
        }
        elseif ($contexts = $this->contextProvider->getRuntimeContexts(['group'])) {
          $context = $contexts['group'];
          $group = $context->getContextValue();
          if (!is_null($group)) {
            $this->setGroupData($notification, $entity, $group, $data);
          }
        }
      }

      // Specific part to use flag module functionality
      if ($this->moduleHandler->moduleExists('flag')
        && !empty($notification->getFlags())) {
        // To get all users who have flagged the entity we need to get the flag.
        $flag_ids = $notification->getFlags();
        /** @var \Drupal\flag\Entity\Flag[] $flags */
        $flags = $this->entityTypeManager
          ->getStorage('flag')
          ->loadMultiple($flag_ids);
        foreach ($flags as $flag) {
          /** @var \Drupal\user\Entity\User[] $users */
          $users = $this->flagService->getFlaggingUsers($entity, $flag);
          foreach ($users as $user) {
            $data['to'][] = $user->id();
          }
        }
      }

      // Let other modules to alter the email data.
      $this->moduleHandler->alter('danse_moderation_notification_mail_data', $entity, $data);

      // Remove any null values that have crept in.
      $data['to'] = array_filter($data['to']);

      // Remove any duplicates.
      $data['to'] = array_unique($data['to']);

      // Force to BCC.
      $data['params']['headers']['Bcc'] = implode(',', $data['to']);

    }
    return $data;
  }

  /**
   * Add the group entity to the mail context in token replacement.
   *
   * @param \Drupal\danse_moderation_notifications\DanseModerationNotificationsInterface $notification
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param \Drupal\group\Entity\Group $group
   * @param array $data
   */
  public function setGroupData(DanseModerationNotificationsInterface $notification, EntityInterface $entity, Group $group, array &$data) {
    if ($this->tokenEntityMapper) {
      $data['params']['context'] = [
        'entity' => $entity,
        'user' => $this->currentUser,
        'group' => $group,
        $this->tokenEntityMapper->getTokenTypeForEntityType($entity->getEntityTypeId()) => $entity,
      ];
    }
    else {
      $data['params']['context'] = [
        'entity' => $entity,
        'user' => $this->currentUser,
        'group' => $group,
        $entity->getEntityTypeId() => $entity,
      ];
    }

    // Get all the group members.
    $notification_roles = array_values(array_filter($notification->getGroupRoles()));
    foreach ($group->getMembers() as $member) {
      // Add user if they have a role from the notification configuration.
      if (array_intersect(array_keys($member->getRoles()), $notification_roles)) {
        $member_user = $member->getUser();
        if ($member_user->isActive()) {
          // Add the group member to the email receiver.
          $data['to'][] = $member_user->id();
        }
      }
    }
  }

}
