<?php

namespace Drupal\epa_workflow\Plugin\views\field;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\views\ResultRow;

/**
 * Provides a field for views to show the content reference's transition states.
 *
 * @ViewsField("epa_workflow_node_transition_to")
 */
class EpaWorkflowNodeTransitionTo extends EpaWorkflowReferenceBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $this->getEntity($values);
    $current_state = '';

    if ($entity instanceof ContentEntityInterface) {
      $current_state = $entity->moderation_state->value;
    }
    return $this->sanitizeValue($current_state);
  }

}
