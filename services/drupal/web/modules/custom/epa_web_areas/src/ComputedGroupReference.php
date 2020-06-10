<?php

namespace Drupal\epa_web_areas;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * A computed property for returning the group entity on content.
 */
class ComputedGroupReference extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * The current active database's master connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(DataDefinitionInterface $definition, $name, TypedDataInterface $parent) {
    parent::__construct($definition, $name, $parent);
    $this->database = \Drupal::database();
  }

  /**
   * Compute the values.
   */
  protected function computeValue() {

    $entity = $this->getEntity();
    $group_content_bundle = 'web_area-group_node-' . $entity->bundle();
    $group_query = $this->database->select('group_content_field_data', 'f')
      ->fields('f', ['gid']);
    $group_query->join('group_content__entity_id', 'e', 'f.id = e.entity_id');
    $group_id = $group_query->condition('e.entity_id_target_id', $entity->id())
      ->condition('f.type', $group_content_bundle)
      ->execute()->fetchCol();
    if ($group_id) {
      $group_id = array_pop($group_id);
      $this->list[] = $this->createItem(0, $group_id);
    }
  }

}
