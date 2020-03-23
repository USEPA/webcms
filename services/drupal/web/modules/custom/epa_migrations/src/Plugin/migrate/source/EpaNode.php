<?php

namespace Drupal\epa_migrations\Plugin\migrate\source;

use Drupal\node\Plugin\migrate\source\d7\Node;
use Drupal\migrate\Row;

/**
 * Load nodes that will be migrated into fields.
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
    // onecol_page or twocol_page. For nodes that use twocol_page and have a
    // pane in the sidebar panel, we'll add 'did' source property so we can
    // process the sidebar content into a paragraph referenced from
    // field_sidebar.
    //
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
        // onecol_page or twocol_page. We'll migrate these nodes with the
        // epa_panelizer source plugin.
        return FALSE;
      }
      elseif ($layout == 'twocol_page') {
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
