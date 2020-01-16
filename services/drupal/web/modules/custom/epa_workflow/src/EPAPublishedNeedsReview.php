<?php

namespace Drupal\epa_workflow;

use DateInterval;
use Drupal\content_moderation\Entity\ContentModerationStateInterface;

/**
 * Processes published content.
 */
class EPAPublishedNeedsReview extends EPAModeration {

  /**
   * {@inheritdoc}
   */
  protected $moderationName = 'published_needs_review';

  /**
   * {@inheritdoc}
   */
  public function process(ContentModerationStateInterface $moderation_entity) {
    parent::process($moderation_entity);

    if ($this->isAutomated) {
      if ($this->contentHasFieldValue('field_review_deadline')) {
        $transition_date = $this->contentEntityRevision->field_review_deadline->date;
        $transition_date = $transition_date->sub(new DateInterval('P1W'));
        $this->scheduleTransition($transition_date, 'published_expiring');
      }
      else {
        $this->logger->warning('An expiration transition for %title could not be set because no review deadline is available.', ['%title' => $this->contentEntityRevision->label()]);
      }
    }
    else {
      // I am not sure this was part of the D7 site.
      $this->clearScheduledTransitions();
    }
  }

}
