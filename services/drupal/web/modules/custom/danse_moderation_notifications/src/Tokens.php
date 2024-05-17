<?php

namespace Drupal\danse_moderation_notifications;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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
   * Constructs the token generation object.
   *
   * @param \Drupal\danse_moderation_notifications\NotificationInformationInterface $notification_information
   *   The notification information service.
   */
  public function __construct(NotificationInformationInterface $notification_information) {
    $this->notificationInformation = $notification_information;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('danse_moderation_notifications.notification_information'));
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

    return $replacements;
  }

}
