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
function epa_workflow_deploy_0028_populate_perspective_author_names_field(&$sandbox) {
  if (!isset($sandbox['total'])) {
    // Query all published perspectives.
    $result = \Drupal::entityQuery('node')
      ->condition('type', 'perspective')
      ->execute();

    $sandbox['total'] = count($result);
    $sandbox['current'] = 0;

    \Drupal::logger('epa_workflow')->notice($sandbox['total'] . ' perspectives.');
  }

  $batch_size = 25;

  // sort nid asc
  // keep track of highest nid
  // increment high water mark
  // can save these values in $sandbox

  // test pub persp that has author but not in new author field
  // make sure after this, it has a new value and is still pub
  $nids = \Drupal::entityQuery('node')
    ->condition('type', 'perspective')
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
    \Drupal::logger('epa_workflow')->notice($node->title->value);
    if (!$node->field_authors->isEmpty()) {
      $author_paragraphs = $node->field_authors->getValue();
      $tids = [];
      foreach ($author_paragraphs as $author) {
        $target_id = $author['target_id'];
        $paragraph = Drupal\paragraphs\Entity\Paragraph::load($target_id);
        $author_tid = $paragraph->field_author->target_id;
        array_push($tids, $author_tid);
      }
      $node->field_author_names = [];
      foreach ($tids as $tid) {
        $node->field_author_names[] = [
          'target_id' => $tid
        ];
      }
    }
    else {
      // Clear new field value if field_authors is empty.
      $node->field_author_names = [];
    }
    $node->save();
    $sandbox['current']++;
  }
}
