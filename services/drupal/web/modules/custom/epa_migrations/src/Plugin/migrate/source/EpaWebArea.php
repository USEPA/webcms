<?php

namespace Drupal\epa_migrations\Plugin\migrate\source;

use Drupal\node\Plugin\migrate\source\d7\Node;
use Drupal\migrate\Row;

/**
 * Load nodes that will be migrated into web area group entities.
 *
 * @MigrateSource(
 *   id = "epa_web_area",
 * )
 */
class EpaWebArea extends Node {

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

    // Fetch the oldest published timestamp for this node id from the
    // node_revision_epa_states_history table.
    $oldest_timestamp = $this->select('node_revision_epa_states_history', 'nrh')
      ->fields('nrh', ['timestamp'])
      ->condition('nrh.nid', $row->getSourceIdValues()['nid'])
      ->condition('nrh.state', 'published')
      ->orderBy('nrh.timestamp')
      ->execute()
      ->fetchField();

    $row->setSourceProperty('oldest_timestamp', $oldest_timestamp);

    parent::prepareRow($row);
  }

}
