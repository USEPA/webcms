<?php

namespace Drupal\epa_workflow\Plugin\views\field;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\views\ResultRow;

/**
 * Provides a field for views to show the content reference's transition states.
 *
 * @ViewsField("epa_workflow_node_transition_from")
 */
class EpaWorkflowNodeTransitionFrom extends EpaWorkflowReferenceBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $this->getEntity($values);
    $previous_state = '';

    if ($entity instanceof ContentEntityInterface) {
      if (isset($entity->last_revision) && !empty($entity->last_revision)) {
        $revision_id = $entity->last_revision;
        /** @var \Drupal\node\Entity\Node $last_revision */
        $last_revision = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->loadRevision($revision_id);
        $previous_state = $last_revision->get('moderation_state')->getString();
      }
    }
    return $this->sanitizeValue($previous_state);
  }

}
