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

    $query->condition('ml.menu_name', 'menu-og-%', 'LIKE');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // Change menu name from 'menu-og-id' to 'group-id-menu'.
    $pattern = '/menu-og-(\d+)/';
    $replacement = 'group-${1}-menu';
    $row->setSourceProperty('d8_menu_name', preg_replace($pattern, $replacement, $row->getSourceProperty('menu_name')));

    return parent::prepareRow($row);
  }

}
