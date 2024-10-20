<?php

namespace Drupal\epa_workflow;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\epa_workflow\EPAWorkflowEmailHandler;

/**
 * Class EPANotificationEmailCron.
 *
 * Queues notification summaries to email to Editors & Deputy Editors in Chief.
 *
 * @package Drupal\epa_workflow
 */
class EPANotificationEmailCron {

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
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface.
   */
  protected $logger;

  /**
   * EPANotificationEmailCron constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MailManagerInterface $mail_manager, LoggerChannelFactoryInterface $logger_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager = $mail_manager;
    $this->logger = $logger_factory->get('epa_workflow');
  }

  /**
   * Get Editors in Chief and deputies.
   *
   * @return \Drupal\epa_workflow\EPANotificationEmailHandler[]
   */
  protected function getGroupEmailData(): array {
    $groups = $this->entityTypeManager->getStorage('group')->loadMultiple();
    $data = [];
    foreach ($groups as $group) {
      $recipients = [];
      $email_handler = new EPAWorkflowEmailHandler();
      /** @var $group \Drupal\group\Entity\Group */
      if ($group->hasField('field_editor_in_chief') && !$group->get('field_editor_in_chief')->isEmpty()) {
        $email_handler->setId($group->id());
        $email_handler->setLabel($group->label());
        $recipients[] = $group->field_editor_in_chief->entity;

        // Get Deputy Editors in Chief.
        $members = $group->getMembers(['web_area-deputy_editor_in_chief']);
        foreach ($members as $member) {
          $recipients[] = $member->getUser();
        }
        $email_handler->setRecipients($recipients);
        $data[] = $email_handler;
      }
    }
    return $data;
  }

  /**
   * Send notification emails.
   */
  public function sendNotificationEmails(): void {
    $env_state = getenv('WEBCMS_ENV_STATE');
    if ($env_state !== 'migration') {
      $emails = $this->getGroupEmailData();
      $module = 'epa_workflow';
      $key = 'epa_workflow_notification_summary';
      $date = new \DateTime();
      $params['date'] = $date->format('F jS, Y');

      foreach ($emails as $email) {
        $params['group_id'] = $email->getId();
        $params['group_label'] = $email->getLabel();
        /** @var \Drupal\user\Entity\User $recipient */
        foreach ($email->getRecipients() as $recipient) {
          $to = $recipient->getEmail();
          $langcode = $recipient->getPreferredLangcode();
          $result = $this->mailManager->mail($module, $key, $to, $langcode, $params);
          if (!$result['result']) {
            $message = t('There was a problem sending your email notification to @email.', ['@email' => $to]);
            $this->logger->error($message);
          }
        }
        break;
      }
    }
  }

}
