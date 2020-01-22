<?php

namespace Drupal\epa_workflow;

use Drupal\content_moderation\Entity\ContentModerationStateInterface;

/**
 * Processes published day til expiring content.
 */
class EPAPublishedDayTilExpire extends EPAModeration {

  /**
   * {@inheritdoc}
   */
  protected $moderationName = 'published_day_til_expire';

  /**
   * {@inheritdoc}
   */
  public function process(ContentModerationStateInterface $moderation_entity) {
    parent::process($moderation_entity);

    if ($this->isAutomated) {
      if ($this->contentHasFieldValue('field_review_deadline')) {
        $transition_date = $this->contentEntityRevision->field_review_deadline->date;
        $this->scheduleTransition($transition_date, 'unpublished');
      }
      else {
        $this->logger->warning('An unpublish transition for %title could not be set because no review deadline is available.', ['%title' => $this->contentEntityRevision->label()]);
      }
    }
  }

}
