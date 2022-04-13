<?php

function _epa_core_populate_search_index_queue() {
  $queue = \Drupal::queue('epa_search_text_indexer');
  // Query all current revisions that lack a value in the search text field
  $current_revs = \Drupal::database()->query(
    "SELECT vid
           FROM {node} n
           LEFT JOIN {node_revision__field_search_text} nf
           ON n.vid = nf.revision_id WHERE nf.revision_id IS NULL")
    ->fetchCol('vid');

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
    ->fetchCol('vid');

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
