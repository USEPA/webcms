<?php

use Drupal\node\NodeInterface;
use Drupal\search_api\Entity\Server;


function _epa_core_populate_search_index_queue() {
  $queue = \Drupal::queue('epa_search_text_indexer');
  // Query all current revisions that lack a value in the search text field
  $current_revs = \Drupal::database()->query(
    "SELECT vid
           FROM {node} n
           LEFT JOIN {node_revision__field_search_text} nf
           ON n.vid = nf.revision_id WHERE nf.revision_id IS NULL")
    ->fetchCol();

  $latest_revs = \Drupal::database()->query(
    "SELECT n.nid, n.vid as vid
          FROM {node_revision} n
          INNER JOIN
              (SELECT nid,
                   max(vid) AS latest_vid
              FROM {node_revision}
              GROUP BY  nid) nr_latest
              ON n.vid = nr_latest.latest_vid
          LEFT JOIN {node_revision__field_search_text} nf
              ON n.vid = nf.revision_id
          WHERE nf.revision_id IS NULL")
    ->fetchCol(1);

  // Remove current revs from latest revs
  $latest_revs = array_diff($latest_revs, $current_revs);

  $current_revs = array_fill_keys($current_revs, 'current');
  $latest_revs = array_fill_keys($latest_revs, 'latest');
  $revisions = $current_revs + $latest_revs;

  \Drupal::logger('epa_core')->notice('Queueing ' . count($revisions) . ' revisions that need to have their search text field populated');

  foreach ($revisions as $vid => $type) {
    $queue->createItem(['vid' => $vid, 'type' => $type]);
  }
}

/**
 * Populates the search text fields for existing content.
 */
function epa_core_deploy_0001_populate_search_text(&$sandbox) {
  _epa_core_populate_search_index_queue();
}

/**
 * Sets terms with empty description to global term description token.
 */
function epa_core_deploy_0001_update_term_descriptions(&$sandbox) {
  $text = 'This page shows all of the pages at epa.gov that are tagged with \[term:name\] at this time.';
  if (!isset($sandbox['total'])) {
    // Query all terms that don't have a description set.
    $result = \Drupal::database()->query(
      'SELECT tid FROM taxonomy_term_field_data
        WHERE description__value IS NULL OR
              description__value = :value OR
              description__value REGEXP :regex', [':value' => 'This page shows all of the pages at epa.gov that are tagged with [term:name] at this time.', ':regex' => '^<p>This page shows all of the pages at epa\\.gov that are tagged with \\[term:name\\] at this time\\.<\\/p>[[:space:]]*$'])
        ->fetchCol();

    $sandbox['total'] = count($result);
    $sandbox['current'] = 0;

    \Drupal::logger('epa_core')->notice($sandbox['total'] . ' terms with outdated descriptions.');
  }

  // Query 500 at a time for batch.
  $tids = \Drupal::database()->query(
    'SELECT tid FROM taxonomy_term_field_data
        WHERE description__value IS NULL OR
              description__value = :value OR
              description__value REGEXP :regex
            LIMIT 500;', [':value' => 'This page shows all of the pages at epa.gov that are tagged with [term:name] at this time.', ':regex' => '^<p>This page shows all of the pages at epa\\.gov that are tagged with \\[term:name\\] at this time\\.<\\/p>[[:space:]]*$'])
    ->fetchCol();

  if (empty($tids)) {
    $sandbox['#finished'] = 1;
    return;
  }

  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadMultiple($tids);

  foreach ($terms as $term) {
    $term->set('description', ['value' => '[term:term-description]', 'format' => 'filtered_html']);
    $term->save();
    $sandbox['current']++;
  }

  \Drupal::logger('epa_core')->notice($sandbox['current'] . ' terms descriptions updated.');

  if ($sandbox['current'] >= $sandbox['total']) {
    $sandbox['#finished'] = 1;
  } else {
    $sandbox['#finished'] = ($sandbox['current'] / $sandbox['total']);
  }
}

/**
 * Explicitly sets each taxonomy term to have its path set by pathauto then re-saves
 * terms to ensure they get the latest generated path.
 */
function epa_core_deploy_0002_update_term_path(&$sandbox) {
  if (!isset($sandbox['total'])) {
    // Query all terms that don't have a description set.
    $result = \Drupal::database()->query(
      'SELECT tid FROM taxonomy_term_field_data;')
      ->fetchCol();

    $sandbox['total'] = count($result);
    $sandbox['current'] = 0;

    \Drupal::logger('epa_core')->notice($sandbox['total'] . ' term paths to be updated.');
  }

  // Query 500 at a time for batch.
  $tids = \Drupal::database()->query(
    'SELECT tid FROM taxonomy_term_field_data
        ORDER BY tid ASC
        LIMIT 500
        OFFSET '. $sandbox['current'] . ';')
    ->fetchCol();

  if (empty($tids)) {
    $sandbox['#finished'] = 1;
    return;
  }

  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadMultiple($tids);

  foreach ($terms as $term) {
    $term->path->pathauto = 1;
    $term->save();
    $sandbox['current']++;
  }

  \Drupal::logger('epa_core')->notice($sandbox['current'] . ' term paths updated.');

  if ($sandbox['current'] >= $sandbox['total']) {
    $sandbox['#finished'] = 1;
  } else {
    $sandbox['#finished'] = ($sandbox['current'] / $sandbox['total']);
  }
}

/**
 * Tag all search indexes as needing reindexing due to the changes to our
 * processor and field settings.
 */
function epa_core_deploy_refresh_indexes() {
  _epa_core_refresh_indexes('localhost');
}

/*
 * Helper function to refresh all search indexes on a server.
 */
function _epa_core_refresh_indexes($server_name) {
  $localhost = Server::load($server_name);
  foreach ($localhost->getIndexes() as $index) {
    $index->reindex();
  }
}

/**
 * Updating the field definition config for node__field_press_office cardinality
 * to be 1.
 */
function epa_core_deploy_0003_update_node_field_press_office_cardinality() {
  $manager = \Drupal::entityDefinitionUpdateManager();
  $storage_definition = $manager->getFieldStorageDefinition('field_press_office', 'node');
  $storage_definition->setCardinality(1);
  $manager->updateFieldStorageDefinition($storage_definition);
}
