<?php

namespace Drupal\epa_migrations\Plugin\migrate\source;

use Drupal\node\Plugin\migrate\source\d7\NodeRevision;
use Drupal\migrate\Row;

/**
 * Load Nodes that will be migrated into Layout Builder.
 *
 * @MigrateSource(
 *   id = "epa_panelizer_node_revision",
 *   source_module = "node"
 * )
 */
class EpaPanelizerNodeRevision extends NodeRevision {

  /**
   * {@inheritDoc}
   */
  protected $batchSize = 1000;

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Get the default Node query.
    $query = parent::query();

    $query->leftJoin('panelizer_entity', 'pe', 'nr.vid = pe.revision_id AND pe.entity_id = n.nid AND pe.entity_type = :type', [':type' => 'node']);
    $query->leftJoin('panels_display', 'pd', 'pe.did = pd.did');
    $query->innerJoin('node_revision_epa_states', 'nres', 'nres.vid = nr.vid');

    $query->addField('nres','state');
    $query->addField('pe', 'did');
    $query->addField('pd', 'layout');

    // Only include records where one of the following is true:
    // * Does not use panelizer twocol_page layout unless it is a web area node
    // * Does not use panelizer onecol_page or six_pack layout.

    $and = $query->andConditionGroup()
      ->condition('pd.layout', 'twocol_page')
      ->condition('n.type', 'web_area');

    $or = $query->orConditionGroup()
      ->condition($and)
      ->condition('pd.layout', ['onecol_page', 'twocol_page', 'six_pack'], 'NOT IN');

    $query->condition($or);

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

    if ($state = $row->getSourceProperty('state')) {
      $state_map = [
        'unpublished' => 'unpublished',
        'draft' => 'draft',
        'published' => 'published',
        'draft_approved' => 'draft_approved',
        'published_review' => 'published_needs_review',
        'published_expire' => 'published_day_til_expire',
        'draft_review' => 'draft_needs_review',
        'queued_for_archive' => 'unpublished',
      ];

      $row->setSourceProperty('nres_state', $state_map[$state]);
    }

    $did = $row->getSourceProperty('did');

    if ($did) {
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

    return parent::prepareRow($row);
  }

}
