<?php

namespace Drupal\epa_workflow;

use DateInterval;
use Drupal\content_moderation\Entity\ContentModerationStateInterface;

/**
 * Processes published and expiring content.
 */
class EPAPublishedExpiring extends EPAModeration {

  /**
   * {@inheritdoc}
   */
  protected $moderationName = 'published_expiring';

  /**
   * {@inheritdoc}
   */
  public function process(ContentModerationStateInterface $moderation_entity) {
    parent::process($moderation_entity);

    if ($this->isAutomated) {
      if ($this->contentHasFieldValue('field_review_deadline')) {
        $transition_date = $this->contentEntityRevision->field_review_deadline->date;
        $transition_date = $transition_date->sub(new DateInterval('P1D'));
        $this->scheduleTransition($transition_date, 'published_day_til_expire');
      }
      else {
        $this->logger->warning('A published, day til expire transition for %title could not be set because no review deadline is available.', ['%title' => $this->contentEntityRevision->label()]);
      }
    }
  }

}
