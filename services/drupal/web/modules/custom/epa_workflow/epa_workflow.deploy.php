<?php

/**
 * Sets all nodes with broken type reference from invalid term to be set to
 * “Overviews and Fact Sheets” term (tid 9).
 */
function epa_workflow_deploy_0001_fix_broken_type_references(&$sandbox) {
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
function epa_workflow_deploy_0002_fix_nodes_without_type(&$sandbox) {
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
function epa_workflow_deploy_0003_fix_nodes_missing_review_deadline(&$sandbox) {
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
function epa_workflow_deploy_0004_fix_nodes_missing_transition_date(&$sandbox) {

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

/**
 * For all existing Perspective, if values exist in field_authors,
 * populate the new taxonomy reference field, field_author_names, with the
 * names of the authors. This elimiates duplicate entities in the dynamic lists.
 */
function epa_workflow_deploy_0006_populate_perspective_author_names_field(&$sandbox) {
  if (!isset($sandbox['total'])) {
    // Query all perspectives.
    $result = \Drupal::database()->query(
      "SELECT nid
              FROM {node}
              WHERE type = 'perspective'
              ORDER BY nid DESC")
      ->fetchCol('nid');

    $sandbox['total'] = count($result);
    $sandbox['current'] = 0;
    $sandbox['highest_nid'] = reset($result);
    $sandbox['high_water_mark'] = 0;

    \Drupal::logger('epa_workflow')->notice($sandbox['total'] . ' perspectives.');
  }

  $nids = \Drupal::database()->query(
    "SELECT nid
          FROM {node}
          WHERE type = 'perspective' AND nid > :high_water_mark AND nid <= :highest_nid
          ORDER BY nid ASC
          LIMIT 25;", [
            ':high_water_mark' => $sandbox['high_water_mark'],
            ':highest_nid' => $sandbox['highest_nid']
          ])
    ->fetchCol('nid');

  if (empty($nids)) {
    $sandbox['#finished'] = 1;
    return;
  }
  else {
    $sandbox['#finished'] = 0;
  }

  /** @var \Drupal\node\NodeInterface[] $nodes */
  $nodes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadMultiple($nids);

  foreach ($nodes as $node) {
    $sandbox['high_water_mark'] = $node->id();
    $node->save();
    $sandbox['current']++;
  }
}

/**
 * Need to migrate the event field `field_date` to a smart date field
 * `field_daterange` to allow for individual items to have selectable timezones
 *
 */
//function epa_workflow_deploy_0008_migrate_field_date_to_field_daterange(&$sandbox) {
//
//  // Need to get the field data to query to build the array.
//  $query = Drupal::database()->select('node__field_date', 'date');
//  $query->addField('date', 'entity_id');
//  $query->addField('date', 'revision_id');
//  $query->addField('date', 'field_date_value');
//  $query->addField('date', 'field_date_end_value');
//  $query->condition('bundle', 'event', '=');
//  $result = $query->execute()->fetchAll();
//
//  $insert = Drupal::database()->insert('node__field_daterange');
//  $insert->fields([
//    'entity_id',
//    'revision_id',
//    'langcode',
//    'delta',
//    'field_daterange_value',
//    'field_daterange_end_value',
//    'field_daterange_duration',
//  ]);
//
//  foreach ($result as $node_field) {
//
//    $date_start = $node_field->field_date_value;
//    $date_end = $node_field->field_date_end_value;
//
//    $date_start = strtotime($date_start);
//    $date_end = strtotime($date_end);
//
//    $duration = ($date_end - $date_start) / 60;
//    $insert->values([
//      $node_field->entity_id,
//      $node_field->revision_id,
//      'en',
//      0,
//      $date_start,
//      $date_end,
//      $duration,
//    ]);
//    $insert->execute();
//  }
//}


/**
 * Need to migrate the event field `field_date` to a smart date field
 * `field_daterange` to allow for individual items to have selectable timezones
 *
 */
function epa_workflow_deploy_0018_migrate_field_data_to_field_daterange(&$sandbox) {

  if (!isset($sandbox['total'])) {

    // Get the total number of records that need
    // tobe migrated.
    $results = Drupal::database()->query(
      "SELECT entity_id
      FROM {node__field_date}
      ORDER BY entity_id DESC"
    )->fetchCol('entity_id');

    $sandbox['total'] = count($results);
    $sandbox['current_processed']= 0;
    $sandbox['max_entity_id'] = reset($results);
    $sandbox['current_entity_id'] = 0;
    $sandbox['batch_count'] = 0;

    \Drupal::logger('epa_workflow')->notice($sandbox['total'] . ' field_date migration.');
  }
  else {
    // Increment this variable to keep track of the batch count.
    $sandbox['batch_count']++;
  }

  $batch = 25;

  // Get
  $query = Drupal::database()->select('node__field_date', 'date');
  $query->addField('date', 'entity_id');
  $query->addField('date', 'revision_id');
  $query->addField('date', 'field_date_value');
  $query->addField('date', 'field_date_end_value');
  $query->condition('bundle', 'event', '=');
  $query->range($sandbox['batch_count'] * $batch,   $batch);
  $records = $query->execute()->fetchAll();


  $insert = Drupal::database()->insert('node__field_daterange');
  $insert->fields([
    'entity_id',
    'revision_id',
    'langcode',
    'delta',
    'field_daterange_value',
    'field_daterange_end_value',
    'field_daterange_duration',
  ]);

  foreach ($records as $node_field) {

    $sandbox['current_entity_id'] = $node_field->entity_id;
    $sandbox['current_processed']++;

    $date_start = $node_field->field_date_value;
    $date_end = $node_field->field_date_end_value;

    $date_start = strtotime($date_start);
    $date_end = strtotime($date_end);

    $duration = ($date_end - $date_start) / 60;
    $insert->values([
      $node_field->entity_id,
      $node_field->revision_id,
      'en',
      0,
      $date_start,
      $date_end,
      $duration,
    ]);
    $insert->execute();
  }

  // If the number of records is less than 25
  if (count($records) < $batch) {
    $sandbox['#finished'] = 1;
  }
  else {
    $sandbox['#finished'] = ($sandbox['current_processed'] / $sandbox['total']);
    // Show the progress of the migration
    \Drupal::logger('epa_workflow')->notice((int) ($sandbox['current_processed'] / $sandbox['total'] * 100) . '% field_date migration.');
  }
}
