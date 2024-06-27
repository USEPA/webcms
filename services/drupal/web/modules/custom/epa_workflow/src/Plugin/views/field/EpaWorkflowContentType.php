<?php

namespace Drupal\epa_workflow\Plugin\views\field;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\views\ResultRow;

/**
 * Provides a field for views to show the content reference's bundle label.
 *
 * @ViewsField("epa_workflow_content_type")
 */
class EpaWorkflowContentType extends EpaWorkflowReferenceBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $this->getEntity($values);
    $value = '';

    if ($entity instanceof ContentEntityInterface) {
      $value = $entity->type->entity->label();
    }

    return $this->sanitizeValue($value);
  }

}
