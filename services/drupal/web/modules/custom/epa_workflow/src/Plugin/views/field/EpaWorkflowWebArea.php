<?php

namespace Drupal\epa_workflow\Plugin\views\field;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\views\ResultRow;

/**
 * Provides a field for views to show the content reference's web area.
 *
 * @ViewsField("epa_workflow_web_area")
 */
class EpaWorkflowWebArea extends EpaWorkflowReferenceBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $this->getEntity($values);
    $value = '';

    if ($entity instanceof ContentEntityInterface
      && $this->moduleHandler->moduleExists('group')) {
      $group_contents = $this->entityTypeManager
        ->getStorage('group_content')
        ->loadByEntity($entity);
      $group_labels = [];
      if ($group_contents) {
        foreach ($group_contents as $group_content) {
          /** @var \Drupal\group\Entity\Group $group */
          $group = $group_content->getGroup();
          $group_labels[] = $group->label();
        }
      }
      $value = implode(', ', $group_labels);
    }

    return $this->sanitizeValue($value);
  }

}
