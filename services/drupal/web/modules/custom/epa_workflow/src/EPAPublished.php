<?php

namespace Drupal\epa_workflow;

use Drupal\content_moderation\Entity\ContentModerationStateInterface;

/**
 * Processes published content.
 */
class EPAPublished extends EPAModeration {

  /**
   * {@inheritdoc}
   */
  protected $moderationName = 'published';

  /**
   * {@inheritdoc}
   */
  public function process(ContentModerationStateInterface $moderation_entity) {
    parent::process($moderation_entity);

    $this->clearScheduledTransitions();

    $this->scheduleTransition('field_expiration_date', 'unpublished');

    $this->setReviewDeadline();

    if ($this->contentHasFieldValue('field_review_deadline')) {
      $transition_date = $this->contentEntityRevision->field_review_deadline->date;
      $transition_date->sub(new \DateInterval("P3W"));
      $this->scheduleTransition($transition_date, 'published_needs_review');
    }
    elseif (!$this->contentHasFieldValue('field_review_deadline')) {
      $this->logger->warning('A review transition for %title could not be set because no review deadline is available.', ['%title' => $this->contentEntityRevision->label()]);
    }
  }

}
