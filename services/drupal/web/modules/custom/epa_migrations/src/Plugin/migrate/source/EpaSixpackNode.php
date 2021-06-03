<?php

namespace Drupal\epa_migrations\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\node\Plugin\migrate\source\d7\Node;
use Drupal\migrate\Row;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Load nodes that will be migrated into fields.
 *
 * @MigrateSource(
 *   id = "epa_sixpack_node",
 *   source_module = "node"
 * )
 */
class EpaSixpackNode extends Node {
  /**
   * {@inheritDoc}
   */
  protected $batchSize = 1000;

  /**
   * Panel keys
   *
   * @link https://git.drupalcode.org/project/clean_markup/-/blob/7.x-2.x/modules/clean_markup_panels/plugins/layouts/six_pack/six_pack.inc#L14
   */
  const SIXPACK_PANEL_KEYS = [
    'first',
    'second',
    'third',
    'fourth',
    'fifth',
    'sixth',
  ];

  /**
   * {@inheritdoc}
   */
  public function query() {
    return self::limitQuery(parent::query());
  }

  static public function limitQuery(SelectInterface $query) {
    // Nodes with six_pack layouts only.
    $query->innerJoin('panelizer_entity', 'pe', 'n.vid = pe.revision_id AND pe.entity_id = n.nid AND pe.entity_type = :type', [':type' => 'node']);
    $query->innerJoin('panels_display', 'pd', 'pe.did = pd.did');
    $query->condition('pe.did', 0, '<>');
    $query->condition('pd.layout', 'six_pack', '=');

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

    // Get the revision moderation state and timestamp.
    $state_data = $this->select('node_revision_epa_states', 'nres')
      ->fields('nres', ['state', 'timestamp'])
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
        'queued_for_archive' => 'unpublished',
      ];

      $row->setSourceProperty('nres_state', $state_map[$state_data['state']]);
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

    // We'll linearize the panels as a single column of paragraphs
    $row->setSourceProperty('layout', 'onecol_page');

    // Get the Display ID for the current revision.
    $did = $this->select('panelizer_entity', 'pe')
      ->fields('pe', ['did'])
      ->condition('pe.revision_id', $row->getSourceProperty('vid'))
      ->condition('pe.revision_id', $row->getSourceProperty('vid'))
      ->condition('pe.entity_id', $row->getSourceProperty('nid'))
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
    foreach (self::SIXPACK_PANEL_KEYS as $region) {
      foreach ($all_panes as $pane) {
        if ($pane['panel'] === $region) {
          $ordered_panes[] = $pane;
        }
      }
    }

    $row->setSourceProperty('main_col_panes', $ordered_panes);
    $row->setSourceProperty('sidebar_panes', []);

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
