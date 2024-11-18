<?php

namespace Drupal\epa_workflow\Plugin\views\field;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base plugin to retrieve content from danse events.
 *
 * @ingroup views_plugins
 */
abstract class EpaWorkflowReferenceTransitionBase extends EpaWorkflowReferenceBase {

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );

    $instance->setModerationInformation($container->get('content_moderation.moderation_information'));

    return $instance;
  }

  /**
   * Sets the moderation information service.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_information
   *   The moderation information service.
   */
  public function setModerationInformation(ModerationInformationInterface $moderation_information) {
    $this->moderationInformation = $moderation_information;
  }

  /**
   * Get last revision ID at time of notification.
   *
   * @param int $id
   *   The revision entity id.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The entity at the time of the revision.
   */
  public function getRevisionEntity($id) {
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('node');
    return $storage->loadRevision($id);
  }

  /**
   * Get the transition revision IDs.
   *
   * @param \Drupal\views\ResultRow $values
   *   The values retrieved from a single row of a view's query result.
   *
   * @return array
   *   The from and to revision IDs for the transition.
   */
  public function getTransitionRevisionIds(ResultRow $values) {
    // Initialize revision_ids.
    $transition_revision_ids = [];

    /** @var \Drupal\danse\Entity\Event $event */
    // Get the event entity.
    $event = $this->getEvent($values);

    // Get the referenced content entity.
    $entity = $this->getEntity($values);

    // If event and entity are not empty, get the revision IDs.
    if (!empty($event)
      && $entity instanceof ContentEntityInterface
    ) {
      $payload = $event->get('payload')->value;
      $payload_values = json_decode($payload, TRUE);
      $transition_revision_ids['from'] = $payload_values['entity']['prev_revision'];

      /** @var \Drupal\node\NodeStorageInterface $node_storage */
      $node_storage = $this->entityTypeManager->getStorage('node');

      $revision_ids = $node_storage->revisionIds($entity);
      $transition_revision_ids['to'] = $this->getTransitionToRevisionId($revision_ids, $transition_revision_ids['from']) ?? end($revision_ids);
    }

    return $transition_revision_ids;
  }

  /**
   * Get the revision ID to transition to.
   *
   * @param array $revision_ids
   *   The revision IDs for the entity.
   * @param int $from_revision_id
   *   The revision ID to transition from.
   *
   * @return int|null
   *   The revision ID to transition to or NULL if not found.
   */
  protected function getTransitionToRevisionId($revision_ids, $from_revision_id) {
    if (empty($from_revision_id)) {
      return NULL;
    }
    $flipped_revision_ids = array_flip($revision_ids);
    $from_revision_key = $flipped_revision_ids[$from_revision_id];
    $to_revision_key = $from_revision_key + 1;
    return $revision_ids[$to_revision_key];
  }

}
