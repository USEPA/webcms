<?php

namespace Drupal\epa_workflow\Plugin\views\field;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a field for views to show the content reference's transition states.
 *
 * @ViewsField("epa_workflow_node_transition")
 */
class EpaWorkflowNodeTransition extends EpaWorkflowReferenceBase {

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
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $this->getEntity($values);
    $transition = '';
    $current_state = '';
    $previous_state = '';

    if ($entity instanceof ContentEntityInterface) {
      $current_state = $entity->moderation_state->value;

      if (isset($entity->last_revision) && !empty($entity->last_revision)) {
        $revision_id = $entity->last_revision;
        /** @var \Drupal\node\Entity\Node $last_revision */
        $last_revision = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->loadRevision($revision_id);
        $previous_state = $last_revision->get('moderation_state')->getString();
      }

      /** @var \Drupal\workflows\WorkflowInterface $workflow */
      $workflow = \Drupal::service('content_moderation.moderation_information')->getWorkflowForEntity($entity);
      $previous_state_label = $workflow->getTypePlugin()->getState($previous_state)->label();
      $current_state_label = $workflow->getTypePlugin()->getState($current_state)->label();

      $transition = 'Transitioned to ' . $previous_state_label . ' from ' . $current_state_label;
    }
    return $this->sanitizeValue($transition);
  }

}
