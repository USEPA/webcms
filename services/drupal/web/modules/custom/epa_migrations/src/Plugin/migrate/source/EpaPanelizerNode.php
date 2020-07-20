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
 * )
 */
class EpaPanelizerNode extends Node {

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

    // If the review deadline is within 30 days of the migration, push out the
    // review deadline by 30 days.
    $review_deadline = $row->getSourceProperty('field_review_deadline')[0]['value'];
    $import_date = strtotime('today');
    $end_import_date = strtotime('+30 days', $import_date);
    $review_deadline = \DateTime::createFromFormat('Y-m-d H:i:s', $review_deadline, new \DateTimeZone('America/New_York'));
    if ($review_deadline) {
      if ($review_deadline->getTimestamp() >= $import_date && $review_deadline->getTimestamp() <= $end_import_date) {
        $review_deadline->setTimestamp(strtotime('+30 days', $review_deadline->getTimestamp()));
      }

      $row->setSourceProperty('field_modified_review_deadline', [0 => ['value' => $review_deadline->format('Y-m-d H:i:s')]]);
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
