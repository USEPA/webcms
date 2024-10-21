<?php

namespace Drupal\epa_workflow\Plugin\views\field;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\views\ResultRow;

/**
 * Provides a field for views to show the content reference's transition states.
 *
 * @ViewsField("epa_workflow_node_transition_to")
 */
class EpaWorkflowNodeTransitionTo extends EpaWorkflowReferenceTransitionBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $this->getEntity($values);
    $current_state = '';

    if ($entity instanceof ContentEntityInterface) {
      $transition_revision_ids = $this->getTransitionRevisionIds($values);

      if (!empty($transition_revision_ids['to'])) {
        $current_revision = $this->getRevisionEntity($transition_revision_ids['to']);
        $current_state = !empty($current_revision) ? $current_revision->get('moderation_state')->getString() : '';
      }
    }
    return $this->sanitizeValue($current_state);
  }

}
