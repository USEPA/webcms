<?php

/**
 * Sets all nodes with broken type reference (either from invalid term or no term
 * at all) to be set to “Overviews and Fact Sheets” term (tid 9).
 */
function epa_workflow_post_update_0001_fix_broken_type_references() {
  // Find all nodes that have a reference to a Type that is an invalid term ID.
  $results = \Drupal::database()
    ->query(
      "SELECT entity_id
             FROM node__field_type
             WHERE field_type_target_id NOT IN (
                SELECT tid from taxonomy_term_data where vid = 'type'
             );")
    ->fetchCol('entity_id');

  // Find all nodes that don't have a Type set at all.
  $results2 = \Drupal::database()
    ->query(
      "SELECT nfd.*
             FROM node_field_data AS nfd
             LEFT JOIN node__field_type AS ft ON nfd.nid = ft.entity_id
             WHERE nfd.status = 1 AND ft.field_type_target_id IS NULL;")
    ->fetchCol('nid');

  $merged = array_merge($results, $results2);



  if (count($merged)) {
    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadMultiple($merged);

    foreach ($nodes as $node) {
      $node->set('field_type', ['target_id' => 9]);
      $node->save();
    }
  }
}

/**
 * Finds all nodes missing a field_review_deadline value and re-saves them.
 */
function epa_workflow_post_update_0002_fix_nodes_missing_review_deadline(&$sandbox) {
  // Re-saving nodes that have a missing field_review_deadline will trigger
  // the EPAPublished service to set the review deadline based on the Type
  // term the node is set to.

  if (!isset($sandbox['total'])) {
      // Query all published nodes that do not have a field_review_deadline value set.
      $result = \Drupal::entityQuery('node')
        ->condition('status', 1)
        ->notExists('field_review_deadline')
        ->execute();

      $sandbox['total'] = count($result);
      $sandbox['current'] = 0;

      \Drupal::logger('epa_workflow')->notice($sandbox['total'] . ' nodes without review_deadline set.');
    }

  $batch_size = 25;

  $nids = \Drupal::entityQuery('node')
    ->condition('status', 1)
    ->notExists('field_review_deadline')
    ->range(0, $batch_size)
    ->execute();

  if (empty($nids)) {
    $sandbox['#finished'] = 1;
    return;
  }

  /** @var \Drupal\node\NodeInterface[] $nodes */
  $nodes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadMultiple($nids);

  foreach ($nodes as $node) {
    $node->save();
    $sandbox['current']++;
  }

  \Drupal::logger('epa_workflow')->notice($sandbox['current'] . ' nodes without review deadline processed.');

  if ($sandbox['current'] >= $sandbox['total']) {
    $sandbox['#finished'] = 1;
  } else {
    $sandbox['#finished'] = ($sandbox['current'] / $sandbox['total']);
  }

}

/**
 * Find all nodes with a review deadline coming up in the next 3 weeks (or in the past)
 * and that don't have a scheduled transition set and re-save them.
 */
function epa_workflow_post_update_0003_fix_nodes_missing_transition_date_three_weeks(&$sandbox) {
  // Create dates 3 weeks in the future from now. We'll use this to find any
  // nodes that have a deadline coming up in the next 3 weeks (or in the past)
  $now = \Drupal\Core\Datetime\DrupalDateTime::createFromTimestamp(time());
  $now->add(new DateInterval('P3W'));
  $formatted_three_weeks = $now->format('Y-m-d\TH:i:s');

  if (!isset($sandbox['total'])) {
    // Query all published nodes that don't have a scheduled transition date, but
    // do have a review deadline
    $result = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('field_review_deadline', $formatted_three_weeks, '<=')
      ->notExists('field_scheduled_transition')
      ->execute();

    $sandbox['total'] = count($result);
    $sandbox['current'] = 0;
  }

  $batch_size = 25;

  $nids = \Drupal::entityQuery('node')
    ->condition('status', 1)
    ->condition('field_review_deadline', $formatted_three_weeks, '<=')
    ->notExists('field_scheduled_transition')
    ->range(0, $batch_size)
    ->execute();

  if (empty($nids)) {
    $sandbox['#finished'] = 1;
    return;
  }

  /** @var \Drupal\node\NodeInterface[] $nodes */
  $nodes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadMultiple($nids);

  foreach ($nodes as $node) {
    // Re-saving nodes that have a missing transition scheduled will trigger
    // the EPAPublished service to set the review deadline based on the Type
    // term the node is set to.
    $node->save();
    $sandbox['current']++;
  }

  Drupal::logger('epa_workflow')->notice($sandbox['current'] . ' nodes with missing scheduled transition processed.');

  if ($sandbox['current'] >= $sandbox['total']) {
    $sandbox['#finished'] = 1;
  } else {
    $sandbox['#finished'] = ($sandbox['current'] / $sandbox['total']);
  }
}


/**
 * Find all nodes missing a scheduled transition and re-saves them.
 */
function epa_workflow_post_update_0004_fix_nodes_missing_scheduled_transition(&$sandbox) {
  if (!isset($sandbox['total'])) {
    // Query all published nodes that don't have a scheduled transition date, but
    // do have a review deadline
    $result = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->notExists('field_scheduled_transition')
      ->execute();

    $sandbox['total'] = count($result);
    $sandbox['current'] = 0;
  }

  $batch_size = 25;

  $nids = \Drupal::entityQuery('node')
    ->condition('status', 1)
    ->notExists('field_scheduled_transition')
    ->range(0, $batch_size)
    ->execute();

  if (empty($nids)) {
    $sandbox['#finished'] = 1;
    return;
  }

  /** @var \Drupal\node\NodeInterface[] $nodes */
  $nodes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadMultiple($nids);

  foreach ($nodes as $node) {
    // Re-saving nodes that have a missing transition scheduled will trigger
    // the EPAPublished service to set the review deadline based on the Type
    // term the node is set to.
    $node->save();
    $sandbox['current']++;
  }

  Drupal::logger('epa_workflow')->notice($sandbox['current'] . ' nodes with missing scheduled transition processed.');

  if ($sandbox['current'] >= $sandbox['total']) {
    $sandbox['#finished'] = 1;
  } else {
    $sandbox['#finished'] = ($sandbox['current'] / $sandbox['total']);
  }
}
