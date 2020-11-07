<?php

namespace Drupal\epa_migrations\Plugin\migrate\source;

use Drupal\node\Plugin\migrate\source\d7\NodeRevision;
use Drupal\migrate\Row;

/**
 * Load node revisions that will be migrated into fields.
 *
 * @MigrateSource(
 *   id = "epa_sixpack_node_revision",
 *   source_module = "node"
 * )
 */
class EpaSixpackNodeRevision extends NodeRevision {

  /**
   * {@inheritDoc}
   */
  protected $batchSize = 1000;

  /**
   * {@inheritdoc}
   */
  public function query() {
    return EpaSixpackNode::limitQuery(parent::query());
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
      ->fields('nres', ['state'])
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
    }

    // We'll linearize the panels as a single column of paragraphs
    $row->setSourceProperty('layout', 'onecol_page');

    // Get the Display ID for the current revision.
    $did = $this->select('panelizer_entity', 'pe')
      ->fields('pe', ['did'])
      ->condition('pe.revision_id', $row->getSourceProperty('vid'))
      ->execute()
      ->fetchField();

    if (!$did) {
      // This should not happen
      return FALSE;
    }

    // All available panes for the did.
    /* @var $all_panes array[] */
    $all_panes = $this->select('panels_pane', 'pp')
      ->fields('pp')
      ->condition('pp.did', $did)
      ->execute()
      ->fetchAll();

    // Picking each in order is just simpler than usort() and still plenty fast
    $ordered_panes = [];
    foreach (EpaSixpackNode::SIXPACK_PANEL_KEYS as $region) {
      foreach ($all_panes as $pane) {
        if ($pane['panel'] === $region) {
          $ordered_panes[] = $pane;
        }
      }
    }

    $row->setSourceProperty('main_col_panes', $ordered_panes);
    $row->setSourceProperty('sidebar_panes', []);

    return parent::prepareRow($row);
  }

}
