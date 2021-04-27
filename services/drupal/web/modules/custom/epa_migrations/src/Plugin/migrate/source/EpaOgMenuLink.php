<?php

namespace Drupal\epa_migrations\Plugin\migrate\source;

use Drupal\menu_link_content\Plugin\migrate\source\MenuLink;
use Drupal\migrate\Row;

/**
 * Drupal menu link source from database.
 *
 * @MigrateSource(
 *   id = "epa_og_menu_link",
 *    source_module = "menu"
 * )
 */
class EpaOgMenuLink extends MenuLink {

  /**
   * {@inheritDoc}
   */
  protected $batchSize = 1000;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();

    $query->leftJoin('og_menu', 'om', 'ml.menu_name = om.menu_name');
    $query->condition('om.menu_name', NULL, 'IS NOT NULL');
    $query->fields('om', ['gid']);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // Standardize D8 menu name to 'group_menu_link_content-id'.
    // Group content menus are created automatically when the groups are created
    // and the ID does not match anything in D7 so we have to look it up.
    $gid = $row->getSourceProperty('gid');
    $menu_entity_id = '';

    $group = $this->entityTypeManager->getStorage('group')->load($gid);
    if ($group) {
      $menus = group_content_menu_get_menus_per_group($group);
      // Assuming there's one menu since we're working with migrated content.
      foreach ($menus as $menu) {
        $menu_entity_id = $menu->entity_id->entity->id();
      }
    }
    $row->setSourceProperty('d8_menu_name', "group_menu_link_content-${menu_entity_id}");

    return parent::prepareRow($row);
  }

}
