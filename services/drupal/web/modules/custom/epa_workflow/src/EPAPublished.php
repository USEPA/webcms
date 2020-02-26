<?php

namespace Drupal\epa_workflow;

use Drupal\content_moderation\Entity\ContentModerationStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

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
    else {
      $this->logger->warning('A review transition for %title could not be set because no review deadline is available.', ['%title' => $this->contentEntityRevision->label()]);
    }

    // We need to record when a Web Area Homepage node is first published.
    if ($this->contentEntityRevision->bundle() == 'web_area' && $this->contentEntityRevision->getEntityTypeId() == 'node') {
      $groups = \Drupal::service('epa_web_areas.web_areas_helper')->getNodeReferencingGroups($this->contentEntityRevision);
      foreach ($groups as $group) {
        if ($group->field_homepage_pub_date->isEmpty()) {
          $date = new DrupalDateTime('now', DateTimeItemInterface::STORAGE_TIMEZONE);
          $format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
          $group->field_homepage_pub_date = $date->format($format);
          $group->save();
        }
      }
    }
  }

}
