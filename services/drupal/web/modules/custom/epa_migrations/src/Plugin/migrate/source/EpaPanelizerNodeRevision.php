<?php

namespace Drupal\epa_migrations\Plugin\migrate\source;

use Drupal\node\Plugin\migrate\source\d7\NodeRevision;
use Drupal\migrate\Row;

/**
 * Load Nodes that will be migrated into Layout Builder.
 *
 * @MigrateSource(
 *   id = "epa_panelizer_node_revision",
 * )
 */
class EpaPanelizerNodeRevision extends NodeRevision {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Get the default Node query.
    $query = parent::query();

    // For all node types, exclude nodes that use these layouts:
    // * onecol_page
    // * six_pack
    // Exclude nodes that use the twocol_page layout for all node types except
    // web_area.
    $query->innerJoin('panelizer_entity', 'pe', 'n.vid = pe.revision_id');
    $query->innerJoin('panels_display', 'pd', 'pe.did = pd.did');
    $query->condition('pe.did', 0, '<>');
    $query->condition('pd.layout', 'onecol_page', '<>');
    $query->condition('pd.layout', 'six_pack', '<>');

    if ($this->configuration['node_type'] !== 'web_area') {
      $query->condition('pd.layout', 'twocol_page', '<>');
    }
    // 53047, 791,
    $query->condition('n.nid', [109], 'IN');

    return $query;
  }

  /**
   * {@inheritDoc}
   */
  public function prepareRow(Row $row) {
    // Always include this fragment at the beginning of every prepareRow()
    // implementation, so parent classes can ignore rows.
    if (parent::prepareRow($row) === FALSE) {
      return FALSE;
    }

    // Get the revision moderation state and timestamp.
    $state_data = $this->select('node_revision_epa_states', 'nres')
      ->fields('nres', ['state', 'timestamp', 'status'])
      ->condition('nres.vid', $row->getSourceProperty('vid'))
      ->execute()
      ->fetchAll();

    if ($state_data) {
      $state_data = array_shift($state_data);
      $state_map = [
        'unpublished' => 'unpublished',
        'draft' => 'draft',
        'published' => 'published',
        'draft_approved' => 'draft_approved',
        'published_review' => 'published_needs_review',
        'published_expire' => 'published_day_til_expire',
        'draft_review' => 'draft_needs_review',
        'queued_for_archive' => 'published_expiring',
      ];

      $row->setSourceProperty('nres_state', $state_map[$state_data['state']]);
      $row->setSourceProperty('nres_revision_status', $state_data['status']);
    }

    // To prepare rows for import into fields, we're going to:
    // - Add a 'layout' source property for use during processing.
    // - Add a 'panes' source property containing query results for all panes.
    //
    // First, initialize the 'layout' source property as NULL so we can properly
    // process nodes that do not have a record in the 'panelizer_entity' table.
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

      // Update the 'layout' property to its actual value.
      $row->setSourceProperty('layout', $layout);

      // Fetch the panes and add the result as a source property.
      $panes = $this->select('panels_pane', 'pp')
        ->fields('pp')
        ->condition('pp.did', $did)
        ->orderBy('pp.position')
        ->orderBy('pp.panel')
        ->execute()
        ->fetchAll();
      $row->setSourceProperty('panes', $panes);
    }

    parent::prepareRow($row);
  }

}
