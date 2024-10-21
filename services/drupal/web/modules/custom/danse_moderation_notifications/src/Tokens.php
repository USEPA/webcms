<?php

namespace Drupal\danse_moderation_notifications;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Drupal\danse\Plugin\PushFrameworkSource\DanseNotification;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Token generation and information class.
 */
class Tokens implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The notification information service.
   *
   * @var \Drupal\danse_moderation_notifications\NotificationInformationInterface
   */
  protected $notificationInformation;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * Constructs the token generation object.
   *
   * @param \Drupal\danse_moderation_notifications\NotificationInformationInterface $notification_information
   *   The notification information service.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(NotificationInformationInterface $notification_information, Token $token) {
    $this->notificationInformation = $notification_information;
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('danse_moderation_notifications.notification_information'),
      $container->get('token')
    );
  }

  /**
   * Token information.
   *
   * @see \danse_moderation_notifications_token_info()
   */
  public static function info() {
    $type = [
      'name' => t('Content moderation states'),
      'description' => t('Content moderation state transition tokens.'),
      'needs-data' => 'entity',
    ];

    $tokens['workflow'] = [
      'name' => t('Workflow'),
      'description' => t('The name of the corresponding workflow.'),
    ];

    $tokens['from-state'] = [
      'name' => t('Old moderation state'),
      'description' => t('The previous state of the moderated content.'),
    ];

    $tokens['to-state'] = [
      'name' => t('Current moderation state'),
      'description' => t('The new/current state of the moderated content.'),
    ];

    $tokens['notification-subject'] = [
      'name' => t('Notification Subject'),
      'description' => t('The email notification subject line used for push framework.'),
    ];

    $tokens['notification-message'] = [
      'name' => t('Notification Message'),
      'description' => t('The email notification body used for push framework.'),
    ];

    return [
      'types' => ['danse_moderation_notifications' => $type],
      'tokens' => [
        'danse_moderation_notifications' => $tokens,
      ],
    ];
  }

  /**
   * Generate tokens.
   *
   * @param string $type
   *   The machine-readable name of the type (group) of token being replaced,
   *   such as 'node', 'user', or another type defined by a hook_token_info()
   *   implementation.
   * @param array $tokens
   *   An array of tokens to be replaced. The keys are the machine-readable
   *   token names, and the values are the raw [type:token] strings that
   *   appeared in the original text.
   * @param array $data
   *   An associative array of data objects to be used when generating
   *   replacement values, as supplied in the $data parameter to
   *   \Drupal\Core\Utility\Token::replace().
   * @param array $options
   *   An associative array of options for token replacement; see
   *   \Drupal\Core\Utility\Token::replace() for possible values.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   Bubbleable metadata.
   *
   * @return array
   *   An associative array of replacement values, keyed by the raw [type:token]
   *   strings from the original text. The returned values must be either plain
   *   text strings, or an object implementing MarkupInterface if they are
   *   HTML-formatted.
   *
   * @see \danse_moderation_notifications_tokens()
   */
  public function getTokens($type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
    $replacements = [];
    if ($type === 'danse_moderation_notifications' && isset($data['entity']) && $data['entity'] instanceof ContentEntityInterface) {
      $entity = $data['entity'];

      if ($this->notificationInformation->isModeratedEntity($entity)) {

        foreach ($tokens as $name => $original) {
          switch ($name) {
            case 'workflow':
              $workflow = $this->notificationInformation
                ->getWorkflow($entity)
                ->label();
              $replacements[$original] = $workflow;
              $bubbleable_metadata->addCacheableDependency($workflow);
              break;

            case 'from-state':
              if ($transition = $this->notificationInformation->getTransition($entity)) {
                $replacements[$original] = $this->notificationInformation->getPreviousState($entity)->label();
                $bubbleable_metadata->addCacheableDependency($transition);
              }
              break;

            case 'to-state':
              if ($transition = $this->notificationInformation->getTransition($entity)) {
                $replacements[$original] = $transition->to()->label();
                $bubbleable_metadata->addCacheableDependency($transition);
              }
              break;
          }
        }
      }
    }

    // Replace tokens in push framework context.
    if ($type === 'danse_moderation_notifications' && !empty($data['push_framework_source_plugin']) && !empty($data['push_framework_source_id'])) {
      $source_plugin = $data['push_framework_source_plugin'];
      $source_id = $data['push_framework_source_id'];

      if ($source_plugin instanceof DanseNotification) {
        $entity = $source_plugin->getObjectAsEntity($source_id);
        $notifications = $this->notificationInformation->getNotifications($entity);

        // We are assuming the latest notification is the only one returned.
        $notification = reset($notifications);

        if ($notification instanceof DanseModerationNotificationsInterface) {
          $token_data = [
            'node' => $entity,
          ];
          foreach ($tokens as $name => $original) {
            switch ($name) {
              case 'notification-subject':
                $replacement = $this->token->replace($notification->getSubject(), $token_data, ['clear' => TRUE]);
                $replacements[$original] = Markup::create($replacement);
                $bubbleable_metadata->addCacheableDependency($notification);
                break;

              case 'notification-message':
                $replacement = $this->token->replace($notification->getMessage(), $token_data, ['clear' => TRUE]);
                $replacements[$original] = Markup::create($replacement);
                $bubbleable_metadata->addCacheableDependency($notification);
                break;
            }
          }
        }
      }
    }

    return $replacements;
  }

}
