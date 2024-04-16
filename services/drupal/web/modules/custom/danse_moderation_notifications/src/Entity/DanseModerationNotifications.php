<?php

namespace Drupal\danse_moderation_notifications\Entity;

use Drupal\danse_moderation_notifications\DanseModerationNotificationsInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the danse_moderation_notifications entity.
 *
 * @see http://previousnext.com.au/blog/understanding-drupal-8s-config-entities
 * @see annotation
 * @see Drupal\Core\Annotation\Translation
 *
 * @ingroup danse_moderation_notifications
 *
 * @ConfigEntityType(
 *   id = "danse_moderation_notifications",
 *   label = @Translation("Notification"),
 *   label_collection = @Translation("Notifications"),
 *   label_singular = @Translation("notification"),
 *   label_plural = @Translation("notifications"),
 *   admin_permission = "administer content moderation notifications",
 *   handlers = {
 *     "access" = "Drupal\danse_moderation_notifications\DanseModerationNotificationsAccessController",
 *     "list_builder" = "Drupal\danse_moderation_notifications\Controller\DanseModerationNotificationsListBuilder",
 *     "form" = {
 *       "add" = "Drupal\danse_moderation_notifications\Form\DanseModerationNotificationsAddForm",
 *       "edit" = "Drupal\danse_moderation_notifications\Form\DanseModerationNotificationsEditForm",
 *       "delete" = "Drupal\danse_moderation_notifications\Form\DanseModerationNotificationsDeleteForm",
 *       "disable" = "Drupal\danse_moderation_notifications\Form\DisableForm",
 *       "enable" = "Drupal\danse_moderation_notifications\Form\DisableForm"
 *     }
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/workflow/notifications/add",
 *     "edit-form" = "/admin/config/workflow/notifications/manage/{danse_moderation_notifications}",
 *     "delete-form" = "/admin/config/workflow/notifications/manage/{danse_moderation_notifications}/delete",
 *     "enable-form" = "/admin/config/workflow/notifications/manage/{danse_moderation_notifications}/enable",
 *     "disable-form" = "/admin/config/workflow/notifications/manage/{danse_moderation_notifications}/disable",
 *     "collection" = "/admin/config/workflow/notifications"
 *   },
 *   config_export = {
 *     "id",
 *     "workflow",
 *     "transitions",
 *     "roles",
 *     "group_type",
 *     "group_use",
 *     "group_roles",
 *     "flags",
 *     "author",
 *     "revision_author",
 *     "site_mail",
 *     "emails",
 *     "subject",
 *     "body",
 *     "label",
 *   }
 * )
 */
class DanseModerationNotifications extends ConfigEntityBase implements DanseModerationNotificationsInterface {

  /**
   * Send notification to the original author.
   *
   * @var bool
   */
  public $author = FALSE;

  /**
   * Send notification to the revision author.
   *
   * @var bool
   */
  public $revision_author = FALSE;

  /**
   * Disable notification to the site mail address.
   *
   * @var bool
   */
  public $site_mail = FALSE;

  /**
   * The notification body value and format.
   *
   * @var array
   */
  public $body = [
    'value' => '',
    'format' => '',
  ];

  /**
   * Additional recipient emails.
   *
   * @var string
   */
  public $emails = '';

  /**
   * The role IDs to send notifications to.
   *
   * @var string[]
   */
  public $roles = [];

  /**
   * The message subject.
   *
   * @var string
   */
  public $subject;

  /**
   * The transition IDs relevant to this notification.
   *
   * @var string[]
   */
  public $transitions = [];

  /**
   * Determines if the notification uses group functionality.
   *
   * @var bool
   */
  public $group_use;

  /**
   * The associated group type for this notification.
   *
   * @var string
   */
  public $group_type;

  /**
   * The group_role IDs to this notification.
   *
   * @var string[]
   */
  public $group_roles = [];

  /**
   * The flag types for this notification.
   *
   * @var string[]
   */
  public $flags = [];

  /**
   * The associated workflow for these notifications.
   *
   * @var string
   */
  public $workflow;

  /**
   * {@inheritdoc}
   */
  public function getWorkflowId() {
    return $this->get('workflow');
  }

  /**
   * {@inheritdoc}
   */
  public function getRoleIds() {
    return $this->get('roles');
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    $this->set('roles', array_filter($this->get('roles')));
    $this->set('transitions', array_filter(($this->get('transitions'))));
    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function isGroupUse() {
    return (bool) $this->get('group_use');
  }

  /**
   * {@inheritdoc}
   */
  public function getTransitions() {
    return $this->get('transitions');
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupType() {
    return $this->get('group_type');
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupRoles() {
    return $this->get('group_roles');
  }

  /**
   * {@inheritDoc}
   */
  public function getFlags() {
    return $this->get('flags');
  }

  /**
   * {@inheritdoc}
   */
  public function getSubject() {
    return $this->get('subject');
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage() {
    return $this->get('body')['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function getMessageFormat() {
    return $this->get('body')['format'];
  }

  /**
   * {@inheritdoc}
   */
  public function getEmails() {
    return $this->get('emails');
  }

  /**
   * {@inheritdoc}
   */
  public function sendToAuthor() {
    return $this->get('author');
  }

  /**
   * {@inheritdoc}
   */
  public function sendToRevisionAuthor() {
    return $this->get('revision_author');
  }

  /**
   * {@inheritdoc}
   */
  public function disableSiteMail() {
    return (bool) $this->get('site_mail');
  }

}
