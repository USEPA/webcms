<?php

/**
 * Sets all nodes with broken type reference from invalid term to be set to
 * “Overviews and Fact Sheets” term (tid 9).
 */
function epa_workflow_post_update_0001_fix_broken_type_references(&$sandbox) {
  if (!isset($sandbox['total'])) {
    // Query all nodes that have a reference to a Type that is an invalid term ID.
    $result = \Drupal::database()->query(
      "SELECT entity_id
             FROM node__field_type
             WHERE field_type_target_id NOT IN (
                SELECT tid from taxonomy_term_data where vid = 'type'
             );")
      ->fetchCol('entity_id');

    $sandbox['total'] = count($result);
    $sandbox['current'] = 0;

    \Drupal::logger('epa_workflow')->notice($sandbox['total'] . ' nodes with an invalid Type set.');
  }

  // Query 25 at a time for batch.
  $nids = \Drupal::database()->query(
    "SELECT entity_id
        FROM node__field_type
        WHERE field_type_target_id NOT IN (
            SELECT tid from taxonomy_term_data where vid = 'type'
        )
        LIMIT 25;")
    ->fetchCol('entity_id');

  if (empty($nids)) {
    $sandbox['#finished'] = 1;
    return;
  }

  /** @var \Drupal\node\NodeInterface[] $nodes */
  $nodes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadMultiple($nids);

  foreach ($nodes as $node) {
    $node->set('field_type', ['target_id' => 9]);
    // On save this will also set a field_review_deadline value and a scheduled transition.
    $node->save();
    $sandbox['current']++;
  }

  \Drupal::logger('epa_workflow')->notice($sandbox['current'] . 'nodes with an invalid Type processed.');

  if ($sandbox['current'] >= $sandbox['total']) {
    $sandbox['#finished'] = 1;
  } else {
    $sandbox['#finished'] = ($sandbox['current'] / $sandbox['total']);
  }
}

/**
 * Sets all nodes with no type reference to be set to “Overviews and Fact Sheets”
 * term (tid 9).
 */
function epa_workflow_post_update_0002_fix_nodes_without_type(&$sandbox) {
  if (!isset($sandbox['total'])) {
    // Query all published nodes that don't have a Type set at all.
    $result = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->notExists('field_type')
      ->execute();

    $sandbox['total'] = count($result);
    $sandbox['current'] = 0;

    \Drupal::logger('epa_workflow')->notice($sandbox['total'] . ' nodes without a Type term set.');
  }

  $batch_size = 25;

  $nids = \Drupal::entityQuery('node')
    ->condition('status', 1)
    ->notExists('field_type')
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
    $node->set('field_type', ['target_id' => 9]);
    // On save this will also set a field_review_deadline value and a scheduled transition.
    $node->save();
    $sandbox['current']++;
  }

  \Drupal::logger('epa_workflow')->notice($sandbox['current'] . ' nodes without a Type processed.');

  if ($sandbox['current'] >= $sandbox['total']) {
    $sandbox['#finished'] = 1;
  } else {
    $sandbox['#finished'] = ($sandbox['current'] / $sandbox['total']);
  }

}

/**
 * Finds all nodes missing a field_review_deadline value and re-saves them.
 */
function epa_workflow_post_update_0003_fix_nodes_missing_review_deadline(&$sandbox) {
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
 * Find all nodes missing a scheduled transition and re-save them.
 */
function epa_workflow_post_update_0004_fix_nodes_missing_transition_date(&$sandbox) {

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
