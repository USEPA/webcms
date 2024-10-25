<?php

namespace Drupal\epa_workflow;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\Renderer;
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
   *  The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * EPANotificationEmailCron constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MailManagerInterface $mail_manager, LoggerChannelFactoryInterface $logger_factory, Renderer $renderer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager = $mail_manager;
    $this->logger = $logger_factory->get('epa_workflow');
    $this->renderer = $renderer;
  }

  /**
   * Get Editors in Chief and deputies.
   *
   * @return \Drupal\epa_workflow\EPANotificationEmailHandler[]
   */
  protected function getGroupEmailData(): array {
//    $groups = $this->entityTypeManager->getStorage('group')->loadMultiple([7, 45061, 141779, 93463]);
    $groups = $this->entityTypeManager->getStorage('group')->loadMultiple();
    $data = [];
    /** @var $group \Drupal\group\Entity\Group */
    foreach ($groups as $group) {
      $expiring_content = $this->getExpiringGroupContent($group->id(), $group->label());
      // Skip group if there is no expired content.
      if (empty($expiring_content)) {
        continue;
      }
      $recipients = [];
      $email_handler = new EPAWorkflowEmailHandler();
      if ($group->hasField('field_editor_in_chief') && !$group->get('field_editor_in_chief')->isEmpty()) {
        $email_handler->setId($group->id());
        $email_handler->setLabel($group->label());
        $email_handler->setBody($expiring_content);
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
        $params['body'] = $email->getBody();
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
      }
    }
  }

  /**
   * Get expiring group content so we can send emails to only groups with results.
   *
   * @param string $group_id
   * @param string $group_label
   *
   * @return string|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getExpiringGroupContent(string $group_id, string $group_label): string|null {
    $view = $this->entityTypeManager
      ->getStorage('view')
      ->load('published_content')
      ->getExecutable();

    // Wrap group parameter value in double quotes to handle groups with commas
    // in the name.
    $gid_parameter = sprintf('"%s (%s)"',$group_label,$group_id);
    // Build exposed filters parameters to send to view.
    $exposed_filters_values = [
      'title' => '',
      'gid' => $gid_parameter,
      'type' => 'All',
      'field_owning_office_target_id' => '',
      'combine' => '',
      'moderation_state' => [
        'epa_default-published_needs_review',
        'epa_default-published_expiring',
        'epa_default-published_day_til_expire',
      ],
      'order' => 'field_review_deadline',
      'sort' => 'desc',
    ];
    $view->initDisplay();
    $view->setDisplay('page_1');
    $view->setItemsPerPage(12);
    $view->setExposedInput($exposed_filters_values);
    $view->execute();

    // If there are no results exit early.
    if (empty($view->result)) {
      return null;
    }

    // Get the View's render array.
    $rendered_view = $view->render();
    // Do not cache the view results as we will build multiple result sets with
    // different criteria. $view->execute() does not invalidate cache and
    // without this change, you will always see the first result set regardless
    // of filter parameters passed.
    $rendered_view['#cache']['max-age'] = 0;
    unset($rendered_view['#pre_render']);
    // Render the array into html.
    $view_html = $this->renderer->render($rendered_view);
    return empty($view_html) ? '' : $view_html->__toString();
  }

}
