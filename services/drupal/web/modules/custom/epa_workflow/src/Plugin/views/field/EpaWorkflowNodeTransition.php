<?php

namespace Drupal\epa_workflow\Plugin\views\field;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\views\ResultRow;

/**
 * Provides a field for views to show the content reference's transition states.
 *
 * @ViewsField("epa_workflow_node_transition")
 */
class EpaWorkflowNodeTransition extends EpaWorkflowReferenceTransitionBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $this->getEntity($values);

    if (empty($entity) || !$entity instanceof ContentEntityInterface) {
      return '';
    }

    $transition_revision_ids = $this->getTransitionRevisionIds($values);

    $transition = '';
    $current_state = '';
    $previous_state = '';

    if (!empty($transition_revision_ids['from'])) {
      $last_revision = $this->getRevisionEntity($transition_revision_ids['from']);
      $previous_state = !empty($last_revision) ? $last_revision->get('moderation_state')->getString() : '';
    }

    if (!empty($transition_revision_ids['to'])) {
      $current_revision = $this->getRevisionEntity($transition_revision_ids['to']);
      $current_state = !empty($current_revision) ? $current_revision->get('moderation_state')->getString() : '';
    }

    if (!empty($current_state) || !empty($previous_state)) {
      /** @var \Drupal\workflows\WorkflowInterface $workflow */
      $workflow = \Drupal::service('content_moderation.moderation_information')->getWorkflowForEntity($entity);

      $transition .= 'Transitioned';

      if (!empty($current_state)) {
        $current_state_label = $workflow->getTypePlugin()->getState($current_state)->label();
        $transition .= ' to ' . $current_state_label;
      }

      if (!empty($previous_state)) {
        $previous_state_label = $workflow->getTypePlugin()->getState($previous_state)->label();
        $transition .= ' from ' . $previous_state_label;
      }
    }

    return $this->sanitizeValue($transition);
  }

}
