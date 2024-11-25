<?php

declare(strict_types=1);

namespace Drupal\epa_workflow\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\epa_workflow\EPAWorkflowEmailHandler;
use Drupal\group\Entity\Group;
use Drupal\shs\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines 'epa_workflow_epa_expiring_content_emails' queue worker.
 *
 * @QueueWorker(
 *   id = "epa_workflow_epa_expiring_content_emails",
 *   title = @Translation("EPA Expiring Content Emails"),
 *   cron = {"time" = 60},
 * )
 */
final class EpaExpiringContentEmails extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new EpaExpiringContentEmails instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MailManagerInterface $mailManager,
    private readonly LoggerChannelInterface $logger,
    private readonly RendererInterface $renderer,
    private readonly Request $request,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.mail'),
      $container->get('logger.factory')->get('epa_workflow'),
      $container->get('renderer'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($group): void {
    /** @var \Drupal\group\Entity\Group $group */
    $env_state = getenv('WEBCMS_ENV_STATE');
    if ($env_state !== 'migration') {
      $email = $this->getGroupEmailData($group);
      $module = 'epa_workflow';
      $key = 'epa_workflow_notification_summary';
      $date = new \DateTime();
      $params['date'] = $date->format('F jS, Y');
      $params['group_id'] = $email->getId();
      $params['group_label'] = $email->getLabel();
      // Nullsafe operator. Return $email if $email is null.
      $params['body'] = $email?->getBody();
      $view_path_with_args_escaped = "admin/content/published?title=&gid=". $email->getViewGidValue(TRUE) . "&type=All&field_owning_office_target_id=&combine=&moderation_state%5B0%5D=epa_default-published_needs_review&moderation_state%5B1%5D=epa_default-published_expiring&moderation_state%5B2%5D=epa_default-published_day_til_expire&order=field_review_deadline&sort=desc";
      $params['view_link'] = sprintf('<p><a href="%s/%s">%s</a></p>', $this->request->getBaseUrl(), $view_path_with_args_escaped, $this->t('View in WebCMS here'));


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

  /**
   * Get Editors in Chief and deputies.
   *
   * @return \Drupal\epa_workflow\EPAWorkflowEmailHandler|NULL
   */
  protected function getGroupEmailData(Group $group): EPAWorkflowEmailHandler|NULL {
    $expiring_content = $this->getExpiringGroupContent($group->id(), $group->label());
    $recipients = [];
    $email_handler = new EPAWorkflowEmailHandler();
    if ($group->hasField('field_editor_in_chief') && !$group->get('field_editor_in_chief')->isEmpty()) {
      $email_handler->setId((int) $group->id());
      $email_handler->setLabel($group->label());
      $email_handler->setBody($expiring_content ?? t('<p>The @web_area web area does not have any content expiring within the next three weeks.</p><p>Thank you!</p>', ['@web_area' => $group->label()])->render());
      $recipients[] = $group->field_editor_in_chief->entity;

      // Get Deputy Editors in Chief.
      $members = $group->getMembers(['web_area-deputy_editor_in_chief']);
      foreach ($members as $member) {
        $recipients[] = $member->getUser();
      }
      $email_handler->setRecipients($recipients);
      return $email_handler;
    }

    return NULL;
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
    /** @var \Drupal\views\ViewExecutable $view */
    $view = $this->entityTypeManager
      ->getStorage('view')
      ->load('published_content')
      ->getExecutable();

    // Wrap group parameter value in double quotes to handle groups with commas
    // in the name.
    $gid_parameter = sprintf('"%s (%s)"', $group_label, $group_id);
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
    $view_html = $this->renderer->renderPlain($rendered_view);
    $rendered = $view_html->__toString();
    if ($view->total_rows > 12) {
      $rendered .= '<p>... and more</p>';
    }
    return empty($view_html) ? '' : $rendered;
  }

}
