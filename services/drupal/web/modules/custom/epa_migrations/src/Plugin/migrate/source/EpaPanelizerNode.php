<?php

namespace Drupal\epa_migrations\Plugin\migrate\source;

use Drupal\node\Plugin\migrate\source\d7\Node;
use Drupal\migrate\Row;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Load Nodes that will be migrated into Layout Builder.
 *
 * @MigrateSource(
 *   id = "epa_panelizer_node",
 *   source_module = "node"
 * )
 */
class EpaPanelizerNode extends Node {

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

    // If the review deadline is on the day of or after the migration, push out
    // the review deadline by 30 days.
    $review_deadline = $row->getSourceProperty('field_review_deadline')[0]['value'];
    $import_date = strtotime('now');
    $review_deadline = \DateTime::createFromFormat('Y-m-d H:i:s', $review_deadline, new \DateTimeZone('America/New_York'));
    if ($review_deadline) {
      if ($review_deadline->getTimestamp() >= $import_date) {
        $review_deadline->setTimestamp(strtotime('+30 days', $review_deadline->getTimestamp()));
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
      // Fetch the node_content pane for the body field.
      $body_pane = $this->select('panels_pane', 'pp')
        ->fields('pp')
        ->condition('pp.did', $did)
        ->condition('pp.type', 'node_content')
        ->orderBy('pp.position')
        ->orderBy('pp.panel')
        ->execute()
        ->fetchAll();
      $row->setSourceProperty('body_pane', $body_pane);

      // Fetch the panes and add the result as a source property.
      // Include the node_content pane (body field) in the panes so we can
      // correctly place it.
      $panes = $this->select('panels_pane', 'pp')
        ->fields('pp')
        ->condition('pp.did', $did)
        ->orderBy('pp.position')
        ->orderBy('pp.panel')
        ->execute()
        ->fetchAll();
      $row->setSourceProperty('panes', $panes);
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

}
