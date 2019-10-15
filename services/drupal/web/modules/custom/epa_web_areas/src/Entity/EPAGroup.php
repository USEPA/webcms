<?php

namespace Drupal\epa_web_areas\Entity;

use Drupal\group\Entity\Group;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Extends entity for group.
 */
class EPAGroup extends Group {

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // If group is updated, group type is web area, and machine name
    // or title is changed bulk update associated group content entities.
    $group_type_id = $this->getGroupTypeId();
    if ($update === TRUE && $group_type_id == 'web_area') {
      if (($this->get('field_machine_name')->isEmpty()
          && $this->matchesOriginal('field_machine_name')
          && $this->label() != $this->original->label())
          || !$this->matchesOriginal('field_machine_name')
      ) {
        $entities = $this->getContentEntities();
        \Drupal::service('epa_web_areas.alias_batch')->startAliasBatch($entities);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupTypeId() {
    return $this->getGroupType()->id();
  }

  /**
   * {@inheritdoc}
   */
  public function matchesOriginal($field_name) {
    return $this->get($field_name)->value == $this->original->get($field_name)->value;
  }

}
