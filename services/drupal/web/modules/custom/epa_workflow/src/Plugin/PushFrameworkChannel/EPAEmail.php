<?php

namespace Drupal\epa_workflow\Plugin\PushFrameworkChannel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\pf_email\Plugin\PushFrameworkChannel\Email;
use Drupal\user\UserInterface;

/**
 * Plugin implementation of the push framework channel.
 *
 * {@inheritdoc}
 */
class EPAEmail extends Email {

  /**
   * {@inheritdoc}
   */
  public function send(UserInterface $user, ContentEntityInterface $entity, array $content, int $attempt): string {
    \Drupal::logger('epa_workflow')->info('EPAEmail::send()');

    return parent::send($user, $entity, $content, $attempt);
  }

}
