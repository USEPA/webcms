<?php

namespace Drupal\Tests\content_moderation_notifications\Kernel;

use Drupal\content_moderation_notifications\Entity\ContentModerationNotification;

/**
 * Helper trait for creating notification entities.
 */
trait ContentModerationNotificationCreateTrait {

  /**
   * Creates a content moderation notification entity with defaults.
   *
   * @param array $values
   *   An array of values. Defaults are provided for any items not passed in.
   *
   * @return \Drupal\content_moderation_notifications\ContentModerationNotificationInterface
   *   The saved entity.
   */
  protected function createNotification(array $values = []) {
    $values += [
      'id' => mb_strtolower($this->randomMachineName()),
      'workflow' => 'editorial',
      'subject' => $this->randomString(),
      'status' => 1,
      'body' => [
        'value' => $this->randomGenerator->paragraphs(2),
        'format' => 'filtered_html',
      ],
      'roles' => [],
      'emails' => '',
      'transitions' => [],
    ];

    $notification = ContentModerationNotification::create($values);
    $notification->save();

    return $notification;
  }

}
