<?php

namespace Drupal\epa_web_areas\Utility;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupContentType;

/**
 * Class WebAreasHelper.
 */
class WebAreasHelper {

  /**
   * Constructs a new WebAreasHelper.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Function checking if web area has a navigation style.
   *
   * @param Drupal\group\Entity\Group $group
   *   The $group enity.
   * @param string $style
   *   The navigation style being tested.
   *
   * @return bool
   *   Returns true if navigation style is selected for group.
   */
  public function checkNavigationStyle(Group $group, $style = 'hublinks') {
    $group_type = $group->getGroupType()->id();
    return $group_type == 'web_area' && $group->field_navigation_style->value == $style;
  }

  /**
   * Function to grab groups associated with node.
   *
   * @return array
   *   Returns array of group entities or empty array.
   */
  public function getNodeReferencingGroups($node) {
    $plugin_id = 'group_node:' . $node->bundle();

    // Only act if there are group content types for this node type.
    $group_content_types = GroupContentType::loadByContentPluginId($plugin_id);
    if (empty($group_content_types) || empty($node->id())) {
      return [];
    }

    // Load all the group content for this node.
    $group_contents = $this->entityTypeManager
      ->getStorage('group_content')
      ->loadByProperties([
        'type' => array_keys($group_content_types),
        'entity_id' => $node->id(),
      ]);

    // If the node does not belong to any group, we have nothing to say.
    if (empty($group_contents)) {
      return [];
    }

    /** @var \Drupal\group\Entity\GroupInterface[] $groups */
    $groups = [];
    foreach ($group_contents as $group_content) {
      /** @var \Drupal\group\Entity\GroupContentInterface $group_content */
      $group = $group_content->getGroup();
      $groups[$group->id()] = $group;
    }
    return $groups;
  }

}
