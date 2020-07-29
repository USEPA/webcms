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
 * )
 */
class EpaNode extends Node {

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
        'queued_for_archive' => 'published_expiring',
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

    // To prepare rows for import into fields, we're going to:
    // - Skip nodes that use panelizer and have a layout other than onecol_page
    //   or twocol_page.
    // - Add a 'layout' source property to populate the 'field_layout'.
    // - Add source properties containing query results for 'main_col' and
    //   'sidebar' panes.
    //
    // First, initialize the 'layout' source property as NULL so we can properly
    // process nodes that do not have a record in the 'panelizer_entith' table.
    $row->setSourceProperty('layout', NULL);

    // Get the Display ID for the current revision.
    $did = $this->select('panelizer_entity', 'pe')
      ->fields('pe', ['did'])
      ->condition('pe.revision_id', $row->getSourceProperty('vid'))
      ->execute()
      ->fetchField();

    // Get the node type from configuration.
    $type = $row->getSourceProperty('type');

    if ($did) {
      // Get the Panelizer layout for this display.
      $layout = $this->select('panels_display', 'pd')
        ->fields('pd', ['layout'])
        ->condition('pd.did', $did)
        ->execute()
        ->fetchField();

      // Determine whether to skip this row, or add data.
      if (!in_array($layout, ['onecol_page', 'twocol_page']) ||
         ($type === 'web_area' && $layout === 'twocol_page')) {
        // Skip this row if:
        // 1. the node uses a panelizer layout other than onecol_page or
        // twocol_page.
        // 2. the node type is web_area and it's using twcol_page layout
        // We'll migrate these nodes with the epa_panelizer_node source plugin.
        return FALSE;
      }
      else {
        // Update the 'layout' property to its actual value.
        $row->setSourceProperty('layout', $layout);

        // Fetch the main_col panes and add the result as a source property.
        $main_col_panes = $this->fetchPanes('main_col', $did);
        $row->setSourceProperty('main_col_panes', $main_col_panes);

        // Fetch the sidebar panes and add the result as a source property.
        $sidebar_panes = $this->fetchPanes('sidebar', $did);
        $row->setSourceProperty('sidebar_panes', $sidebar_panes);

      }
    }

    parent::prepareRow($row);
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
      ->execute()
      ->fetchAll();
  }

}
