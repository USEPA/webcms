<?php

namespace Drupal\epa_workflow\Plugin\views\field;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\views\ResultRow;

/**
 * Provides a field for views to show the content reference's transition states.
 *
 * @ViewsField("epa_workflow_node_transition_from")
 */
class EpaWorkflowNodeTransitionFrom extends EpaWorkflowReferenceTransitionBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $this->getEntity($values);
    $previous_state = '';

    if ($entity instanceof ContentEntityInterface) {
      $transition_revision_ids = $this->getTransitionRevisionIds($values);

      if (!empty($transition_revision_ids['from'])) {
        $last_revision = $this->getRevisionEntity($transition_revision_ids['from']);
        $previous_state = !empty($last_revision) ? $last_revision->get('moderation_state')->getString() : '';
      }
    }
    return $this->sanitizeValue($previous_state);
  }

}
