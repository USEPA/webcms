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
    // Standardize D8 menu name to 'group-id-menu'.
    $gid = $row->getSourceProperty('gid');
    $row->setSourceProperty('d8_menu_name', "group-${gid}-menu");

    return parent::prepareRow($row);
  }

}
