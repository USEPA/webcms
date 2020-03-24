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

    // To prepare rows for import into fields, we're going to:
    //
    // 1. Skip nodes that use panelizer and have a layout other than
    // onecol_page or twocol_page.
    //
    // 2. Add a 'did' source property that can be used during the process phase.
    //
    // 3. Add a 'layout' source property to populate the 'field_layout'.
    //
    // First, initialize the 'did' and 'layout' source properties as NULL so we
    // can properly process nodes that do not have a record in the
    // 'panelizer_entith' table.
    $row->setSourceProperty('did', NULL);
    $row->setSourceProperty('layout', NULL);

    // Get the Display ID for the current revision.
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
      else {
        // Update the 'did' and 'layout' properties to their stored values.
        $row->setSourceProperty('did', $did);
        $row->setSourceProperty('layout', $layout);
      }
    }

    parent::prepareRow($row);
  }

}
