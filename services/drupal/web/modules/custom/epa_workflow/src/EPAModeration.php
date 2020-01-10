<?php

namespace Drupal\epa_workflow;

use Drupal\content_moderation\Entity\ContentModerationStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Provides abstract class for post moderation processes.
 */
abstract class EPAModeration implements EPAModerationInterface {

  /**
   * The name of the moderation state being processed.
   *
   * @var string
   */
  protected $moderationName = '';

  /**
   * The content moderation entity.
   *
   * @var \Drupal\content_moderation\Entity\ContentModerationStateInterface
   */
  protected $moderationEntity;

  /**
   * The content entity revision pulled from moderation state.
   *
   * @var Drupal\Core\Entity\ContentEntityInterface
   */
  protected $contentEntityRevision;

  /**
   * Flag indicating if transition is automated.
   *
   * @var bool
   */
  protected $isAutomated = FALSE;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The workflow storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $workflowStorage;

  /**
   * Constructs EPAModeration.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->logger = $logger_factory->get('epa_workflow');
    $this->entityTypeManager = $entity_type_manager;
    $this->workflowStorage = $entity_type_manager->getStorage('workflow');
  }

  /**
   * Process given moderation state entity.
   *
   * @param Drupal\content_moderation\Entity\ContentModerationStateInterface $moderation_entity
   *   The moderation state.
   */
  public function process(ContentModerationStateInterface $moderation_entity) {
    $this->moderationEntity = $moderation_entity;
    $this->setContentEntityRevision();
    $this->contentEntityRevision->setSyncing(TRUE);
    if ($this->contentEntityRevision->epa_revision_automated->value) {
      $this->isAutomated = TRUE;
      $this->contentEntityRevision->set('epa_revision_automated', NULL);
    }
  }

  /**
   * Save content entity revision.
   */
  public function save() {
    $this->contentEntityRevision->save();
  }

  /**
   * Get moderation name.
   */
  public function getModerationName() {
    return $this->moderationName;
  }

  /**
   * Log transition.
   */
  public function logTransition() {
    $moderation_label = strtolower($this->getModerationLabel());
    $this->logger->notice('Content was transitioned to %moderation_label', ['%moderation_label' => $moderation_label]);
  }

  /**
   * Schedule transition for content entity.
   *
   * @param mixed $transition_date
   *   The scheduled transition date as string or DateTime.
   * @param string $moderation_state
   *   The scheduled moderation state.
   */
  protected function scheduleTransition($transition_date, $moderation_state) {
    // Set date value.
    if (is_string($transition_date)) {
      // Stop if content doesn't have a publish date.
      if (!$this->contentEntityRevision->hasField($transition_date) || $this->contentEntityRevision->get($transition_date)->isEmpty()) {
        return;
      }
      $date = $this->contentEntityRevision->{$transition_date}->value;
    }
    else {
      // We are expecting a datetime object here.
      $date = $transition_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    }

    // Set scheduled transition.
    if ($this->contentEntityRevision->hasField('field_scheduled_transition')) {
      $new_transition = [
        'moderation_state' => $moderation_state,
        'value' => $date,
      ];
      $this->contentEntityRevision->field_scheduled_transition->appendItem($new_transition);
    }

    $timestamp = strtotime($date);

    // Log scheduled transition.
    $this->logger->notice('%title will be transitioned from %current_state to %target_state on %date.', [
      '%title' => $this->contentEntityRevision->label(),
      '%current_state' => $this->getModerationLabel(),
      '%target_state' => $moderation_state,
      '%date' => date('m-d-Y H:i:s', $timestamp),
    ]);
  }

  /**
   * Sets field_review_deadline value based on metadata type.
   *
   * @param bool $reset
   *   Indicates whether review deadline is set or cleared.
   */
  protected function setReviewDeadline($reset = FALSE) {
    // Stop if content doesn't use a review deadline.
    if (!$this->contentEntityRevision->hasField('field_review_deadline') || !$this->contentEntityRevision->get('field_review_deadline')->isEmpty()) {
      return;
    }

    if ($reset) {
      $this->contentEntityRevision->set('field_review_deadline', NULL);
      return;
    }

    if (!$this->contentEntityRevision->get('field_type')->isEmpty()
        && !$this->contentEntityRevision->get('field_type')->entity->get('field_term_days_til_review')->isEmpty()
    ) {
      // Create datetime with appropriate offset.
      $review_period = $this->contentEntityRevision->field_type->entity->field_term_days_til_review->value;
      $date = new DrupalDateTime();
      $date->setTimeZone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
      $date->add(new \DateInterval("P{$review_period}D"));
      $review_deadline = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
      $this->contentEntityRevision->set('field_review_deadline', $review_deadline);
    }
    else {
      $this->logger->warning('A review deadline for %title could not be set because an invalid or missing type was defined.', ['%title' => $this->contentEntityRevision->label()]);
    }
  }

  /**
   * Clear scheduled transitions on content entity revision.
   *
   * @param string $moderation_state
   *   The name of the moderation state.
   *
   * @todo Might have to run through all revisions to clear them.
   * What would happen if an old revision becomes the default?
   * scheduled_publish when transitioning only cares about the default revision.
   */
  protected function clearScheduledTransitions($moderation_state = NULL) {
    if ($this->contentEntityRevision->hasField('field_scheduled_transition')) {
      if (!empty($moderation_state)) {
        foreach ($this->contentEntityRevision->field_scheduled_transition as $key => $scheduled_transition) {
          if ($scheduled_transition->get('moderation_state') == $moderation_state) {
            $this->contentEntityRevision->field_scheduled_transition->removeItem($key);
          }
        }
      }
      else {
        $this->contentEntityRevision->set('field_scheduled_transition', NULL);
      }
    }
  }

  /**
   * Get content entity revision from moderation state.
   */
  protected function setContentEntityRevision() {
    $content_entity_type = $this->moderationEntity->content_entity_type_id->value;
    $content_entity_revision_id = $this->moderationEntity->content_entity_revision_id->value;

    $content_entity_revision = $this->entityTypeManager->getStorage($content_entity_type)->loadRevision($content_entity_revision_id);

    $this->contentEntityRevision = $content_entity_revision;
  }

  /**
   * Checks if field exists and is not empty on revision.
   */
  protected function contentHasFieldValue($field_name) {
    return $this->contentEntityRevision->hasField($field_name) && !$this->contentEntityRevision->get($field_name)->isEmpty();
  }

  /**
   * Returns bundle label for moderation state.
   */
  protected function getModerationLabel() {
    $workflow_id = $this->moderationEntity->workflow->target_id;
    $workflow = $this->workflowStorage->load($workflow_id);
    $state = $this->moderationEntity->moderation_state->value;
    return $workflow->getTypePlugin()->getState($state)->label();
  }

}
