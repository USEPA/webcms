<?php

namespace Drupal\epa_migrations\Plugin\migrate\source;

use Drupal\node\Plugin\migrate\source\d7\Node;
use Drupal\migrate\Row;

/**
 * EPA Drupal 7 node source from database.
 *
 * @MigrateSource(
 *   id = "epa_node",
 * )
 */
class EpaNode extends Node {

  /**
   * {@inheritDoc}
   */
  public function prepareRow(Row $row) {
    // Always include this fragment at the beginning of every prepareRow()
    // implementation, so parent classes can ignore rows.
    if (parent::prepareRow($row) === FALSE) {
      return FALSE;
    }

    // We're going to skip nodes that use panelizer and have a layout other than
    // onecol_page or twocol_page. Those nodes will be migrated using the
    // epa_panelizer_node source plugin.

    // Get the display ID for the current revision.
    $did = $this->select('panelizer_entity', 'pe')
      ->fields('pe', ['did'])
      ->condition('pe.revision_id', $row->getSourceProperty('vid'))
      ->execute()
      ->fetchField();

    if ($did) {
      // Get the Panelizer layout for this display.
      $layout = $this->select('panels_display', 'pd')
        ->fields('pd', ['layout'])
        ->condition('pd.did', $did)
        ->execute()
        ->fetchField();

      if (!in_array($layout, ['onecol_page', 'twocol_page'])) {
        // Skip this row if this node uses a panelizer layout other than
        // onecol_page or twocol_page.
        return FALSE;
      }
      else {
        // Check if this row has content in the Sidebar pane. If so, add the
        // Display ID so we can process that content during the migration.
        $sidebar_panes = $this->select('panels_pane', 'pp')
          ->fields('pp', ['panel'])
          ->condition('pp.did', $did)
          ->condition('pp.panel', 'sidebar')
          ->execute()
          ->fetchField();

        if ($sidebar_panes) {
          $row->setSourceProperty('did', $did);
        }
      }
    }

    // If this node does not use panelizer or it is using the 'onecol_page' or
    // 'twocol_page' layout, then we will migrate it.
    parent::prepareRow($row);
  }

}
