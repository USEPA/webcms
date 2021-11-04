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

    // Clear scheduled transition date
    $this->clearScheduledTransitions($this->moderationName);

    // Clear out the review deadline
    $this->setReviewDeadline(TRUE);
  }

}
