<?php

namespace Drupal\danse_moderation_notifications;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines a content moderation notification interface.
 */
interface DanseModerationNotificationsInterface extends ConfigEntityInterface {

  /**
   * Get the email addresses.
   *
   * @return string
   *   The email addresses (comma-separated) for which to send the notification.
   */
  public function getEmails();

  /**
   * Send the notification to the entity author.
   *
   * @return bool
   *   Returns TRUE if the notification should be sent to the entity author.
   */
  public function sendToAuthor();
   /**
    * Send the notification to the revision author.
    *
    * @return bool
    *   Returns TRUE if the notification should be sent to the revision author.
    */
  public function sendToRevisionAuthor();

  /**
   * Send the notification to the site mail address.
   *
   * @return bool
   *   Returns FALSE if the notification should be sent to site mail address.
   */
  public function disableSiteMail();

  /**
   * Gets the workflow ID.
   *
   * @return string
   *   The workflow ID.
   */
  public function getWorkflowId();

  /**
   * Gets the relevant roles for this notification.
   *
   * @return string[]
   *   The role IDs that should receive notification.
   */
  public function getRoleIds();

  /**
   * Get the transitions for which to send this notification.
   *
   * @return string[]
   *   The relevant transitions.
   */
  public function getTransitions();

  /**
   * Determines if notification uses group functionality.
   *
   * @return bool
   *   The group_use.
   */
  public function isGroupUse();

  /**
   * Get the group content types for which to send this notification.
   *
   * @return string
   *   The relevant group content types.
   */
  public function getGroupType();

  /**
   * Get the group_role for which to send this notification.
   *
   * @return string[]
   *   The relevant group_role.
   */
  public function getGroupRoles();

  /**
   * Get the flag types for which to send this notification.
   *
   * @return string[]
   */
  public function getFlags();

  /**
   * Gets the notification subject.
   *
   * @return string
   *   The message subject.
   */
  public function getSubject();

  /**
   * Gets the message value.
   *
   * @return string
   *   The message body text.
   */
  public function getMessage();

  /**
   * Gets the message format.
   *
   * @return string
   *   The format to be used for the message body.
   */
  public function getMessageFormat();

}
