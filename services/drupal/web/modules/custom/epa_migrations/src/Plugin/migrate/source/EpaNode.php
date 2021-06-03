<?php

namespace Drupal\epa_migrations\Plugin\migrate\source;

use Drupal\node\Plugin\migrate\source\d7\Node;
use Drupal\migrate\Row;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Load nodes that will be migrated into fields.
 *
 * @MigrateSource(
 *   id = "epa_node",
 *   source_module = "node"
 * )
 */
class EpaNode extends Node {

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

    $query->leftJoin('panelizer_entity', 'pe', 'n.vid = pe.revision_id AND pe.entity_id = n.nid AND pe.entity_type = :type', [':type' => 'node']);
    $query->leftJoin('panels_display', 'pd', 'pe.did = pd.did');
    $query->innerJoin('node_revision_epa_states', 'nres', 'nres.vid = nr.vid');

    $query->addField('nres','state');
    $query->addField('pe', 'did');
    $query->addField('pd', 'layout');

    // Only include records where one of the following is true:
    // * There's no layout record (no panelizer override)
    // * Uses panelizer onecol_page layotu
    // * Users panelizer twocol_page layout and IS NOT a web area node
    $and = $query->andConditionGroup()
      ->condition('pd.layout', 'twocol_page')
      ->condition('n.type', 'web_area', '!=');
    $or = $query->orConditionGroup()
      ->condition('pe.did', NULL, 'IS NULL')
      ->condition('pe.did', 0)
      ->condition('pd.layout', 'onecol_page')
      ->condition($and);

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

    // If the review deadline is on the day of or after the migration, push out
    // the review deadline by 60 days.
    $review_deadline = $row->getSourceProperty('field_review_deadline')[0]['value'];
    $import_date = strtotime('now');
    $review_deadline = \DateTime::createFromFormat('Y-m-d H:i:s', $review_deadline, new \DateTimeZone('America/New_York'));
    if ($review_deadline) {
      if ($review_deadline->getTimestamp() >= $import_date) {
        $review_deadline->setTimestamp(strtotime('+60 days', $review_deadline->getTimestamp()));
      }

      $row->setSourceProperty('field_modified_review_deadline', [0 => ['value' => $review_deadline->format('Y-m-d H:i:s')]]);
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

    // Get the timestamp for the latest published revision.
    $last_published = $this->select('node_revision_epa_states_history', 'nresh')
      ->fields('nresh', ['timestamp'])
      ->condition('nresh.nid', $row->getSourceProperty('nid'))
      ->condition('nresh.state', 'published')
      ->orderBy('nresh.timestamp', 'DESC')
      ->execute()
      ->fetchField();

    if ($last_published) {
      $row->setSourceProperty('last_published', gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $last_published));
    }

    $did = $row->getSourceProperty('did');

    if ($did) {
      // Fetch the main_col panes and add the result as a source property.
      $main_col_panes = $this->fetchPanes('main_col', $did);
      $row->setSourceProperty('main_col_panes', $main_col_panes);

      // Fetch the sidebar panes and add the result as a source property.
      $sidebar_panes = $this->fetchPanes('sidebar', $did);
      $row->setSourceProperty('sidebar_panes', $sidebar_panes);
    }
    else {
      // Return the body content as 'main_col_panes' so it can be converted to
      // a paragraph.
      $row->setSourceProperty('main_col_panes', $row->getSourceProperty('body'));
    }

    // Add node alias.
    $query = $this->select('url_alias', 'ua')
      ->fields('ua', ['alias']);
    $query->condition('ua.source', 'node/' . $row->getSourceProperty('nid'));
    $alias = $query->execute()->fetchField();
    if (!empty($alias)) {
      $row->setSourceProperty('alias', '/' . $alias);
    }

    return parent::prepareRow($row);
  }

  /**
   * Given a panel machine name and did, fetch panes.
   *
   * @param string $panel
   *   The machine name of the panel from which to select panes.
   * @param int $did
   *   The Display ID for this node.
   *
   * @return \Drupal\Core\Database\StatementInterface|null
   *   A prepared statement, or NULL if the query is not valid.
   */
  private function fetchPanes($panel, $did) {
    return $this->select('panels_pane', 'pp')
      ->fields('pp')
      ->condition('pp.did', $did)
      ->condition('pp.panel', $panel)
      ->orderBy('pp.position')
      ->execute()
      ->fetchAll();
  }

}
