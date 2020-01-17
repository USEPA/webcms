<?php

namespace Drupal\epa_workflow;

use Drupal\content_moderation\Entity\ContentModerationStateInterface;

/**
 * Processes unpublished content.
 */
class EPAUnpublished extends EPAModeration {

  /**
   * {@inheritdoc}
   */
  protected $moderationName = 'unpublished';

  /**
   * {@inheritdoc}
   */
  public function process(ContentModerationStateInterface $moderation_entity) {
    parent::process($moderation_entity);

    $this->clearScheduledTransitions();

    $this->setReviewDeadline(TRUE);
  }

}
