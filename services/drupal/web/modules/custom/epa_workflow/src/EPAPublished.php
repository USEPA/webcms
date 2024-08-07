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

    $env_state = getenv('WEBCMS_ENV_STATE');
    if ($env_state !== 'migration' && $this->contentEntityRevision->getEntityTypeId() == 'node') {
      $node = $this->contentEntityRevision;
      if ($node->bundle() == 'public_notice') {
        // Automatically populate notice's field_geographic_locations field with
        // data from field_location_of_prop_action to avoid making users enter it
        // twice.
        $geo_location_terms = [];
        if (!$node->field_locations_of_prop_actions->isEmpty()) {
          foreach ($node->field_locations_of_prop_actions as $item) {
            if (is_object($item->entity)) {
              $location = $item->entity;
              if (!$location->field_state_or_territory->isEmpty()) {
                $geo_location_terms[] = [
                  'target_id' => $location->field_state_or_territory->target_id,
                ];
              }
            }
          }
        }
        $node->set('field_geographic_locations', $geo_location_terms);

        // Set computed date based on extension and due date. If neither are set, clear it.
        // If only extension date is set, do nothing.
        $due_date = $node->field_comments_due_date->value;
        $extension_date = $node->field_comments_extension_date->value;

        if ($due_date) {
          if ($extension_date) {
            $node->set('field_computed_comments_due_date', $extension_date);
          }
          else {
            $node->set('field_computed_comments_due_date', $due_date);
          }
        }
        else {
          $node->set('field_computed_comments_due_date', NULL);
        }

        // If computed date was set, use it. Otherwise set a date 90 days out.
        if ($computed_date = $node->field_computed_comments_due_date->date) {
          $node->set('field_notice_sort_date', $computed_date->format(DateTimeItemInterface::DATE_STORAGE_FORMAT));

          $expiration_date = new DrupalDateTime($computed_date->format(DateTimeItemInterface::DATE_STORAGE_FORMAT). 'T23:59:59', 'America/New_York');
          //  Schedule the node to be unpublished 5 days after it closes for
          // comments. See https://forumone.atlassian.net/browse/EPAD8-2155
          $expiration_date->add(new \DateInterval("P5D"));
          $node->set('field_expiration_date', $expiration_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, ['timezone' => 'UTC']));
        }
        else {
          $date = new DrupalDateTime();
          $date->add(new \DateInterval("P90D"));

          // Set field value.
          $node->set('field_notice_sort_date', $date->format(DateTimeItemInterface::DATE_STORAGE_FORMAT));
          $node->set('field_expiration_date', NULL);
        }
      }
      if ($node->bundle() == 'news_release') {
        $term = $this->entityTypeManager->getStorage('taxonomy_term')
          ->loadByProperties(['name' => 'news release', 'vid' => 'type']);
        if (!empty($term)) {
          $term = reset($term);
          $node->field_type = $term->id();
        }
      }
    }

    $this->setReviewDeadline();
    $this->setLastPublishedDate();

    if ($this->contentEntityRevision->hasField('field_review_deadline') &&
      $this->contentEntityRevision->hasField('field_expiration_date') &&
      !$this->contentEntityRevision->get('field_expiration_date')->isEmpty() &&
      (
        !$this->contentHasFieldValue('field_review_deadline') ||
        $this->contentEntityRevision->field_review_deadline->value > $this->contentEntityRevision->field_expiration_date->value
      )) {
      $this->scheduleTransition('field_expiration_date', 'unpublished', TRUE);
    }

    if ($this->contentHasFieldValue('field_review_deadline')) {
      $transition_date = $this->contentEntityRevision->field_review_deadline->date;
      $transition_date->sub(new \DateInterval("P3W"));
      $this->scheduleTransition($transition_date, 'published_needs_review');
    }
    else {
      $this->logger->warning('A review transition for %title could not be set because no review deadline is available.', ['%title' => $this->contentEntityRevision->label()]);
    }
  }

}
