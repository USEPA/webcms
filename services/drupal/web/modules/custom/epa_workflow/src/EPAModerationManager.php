<?php

namespace Drupal\epa_workflow;

use Drupal\content_moderation\Entity\ContentModerationStateInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Handles moderation events.
 */
class EPAModerationManager {

  /**
   * The entity type manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * List of moderations to process.
   *
   * @var \Drupal\epa_workflow\EPAModerationInterface
   */
  protected $moderations = [];

  /**
   * EPAModerationManager constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModerationInformationInterface $moderation_information) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moderationInformation = $moderation_information;
  }

  /**
   * Perform tasks after entity is moderated.
   *
   * @param Drupal\content_moderation\Entity\ContentModerationStateInterface $moderation_entity
   *   The moderation state.
   */
  public function processModeration(ContentModerationStateInterface $moderation_entity) {
    $state = $moderation_entity->moderation_state->value;
    foreach ($this->moderations as $moderation) {
      if ($state == $moderation->getModerationName()) {
        $moderation->process($moderation_entity);
        $moderation->save();
        $moderation->logTransition();
      }
    }
  }

  /**
   * Adds a moderation.
   */
  public function addModeration(EPAModerationInterface $moderation) {
    $this->moderations[] = $moderation;
  }

  /**
   * Check if content is moderated.
   *
   * @param Drupal\content_moderation\Entity\ContentModerationStateInterface $moderation_entity
   *   The moderation state.
   */
  public function isModeratedEntity(ContentModerationStateInterface $moderation_entity) {
    $content_entity_type_id = $moderation_entity->content_entity_type_id->value;
    $content_entity_type = $this->entityTypeManager->getStorage($content_entity_type_id)->getEntityType();
    return $this->moderationInformation->isModeratedEntityType($content_entity_type);
  }

  /**
   * Get content entity revision from moderation state.
   *
   * @param Drupal\content_moderation\Entity\ContentModerationStateInterface $moderation_entity
   *   The moderation state.
   *
   * @return Drupal\Core\Entity\ContentEntityInterface
   *   Return latest content entity revision.
   */
  public function getContentEntityRevision(ContentModerationStateInterface $moderation_entity) {
    $content_entity_type = $moderation_entity->content_entity_type_id->value;
    $content_entity_revision_id = $moderation_entity->content_entity_revision_id->value;

    $content_entity_revision = $this->entityTypeManager->getStorage($content_entity_type)->loadRevision($content_entity_revision_id);

    return $content_entity_revision;
  }

  public function filterTransitionOptions(EntityInterface $entity, array $states) {
    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
    $workflow_states = $workflow->get('type_settings')['states'];
    $default_states = array_filter($workflow_states, function ($state, $key) {
      return $key === 'published' || !$state['default_revision'];
    }, ARRAY_FILTER_USE_BOTH);
    return array_intersect_key($states, $default_states);
  }

}
