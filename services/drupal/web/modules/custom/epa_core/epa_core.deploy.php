<?php

/**
 * Populates the search text fields for existing content.
 */
function epa_core_deploy_0001_populate_search_text(&$sandbox) {
  $lang = getenv('WEBCMS_LANG');
  if (!isset($sandbox['total'])) {
    // Query all current revisions that lack a value in the search text field
    $current_revs = \Drupal::database()->query(
      "SELECT vid
             FROM {node} n
             LEFT JOIN {node_revision__field_search_text} nf
             ON n.vid = nf.revision_id WHERE nf.revision_id IS NULL")
      ->fetchCol('vid');

    $latest_revs = \Drupal::database()->query(
      "SELECT nid, max(vid) as latest_vid
             FROM {node_revision} n
             LEFT JOIN {node_revision__field_search_text} nf
             ON n.vid = nf.revision_id WHERE nf.revision_id IS NULL GROUP BY nid")
      ->fetchCol('latest_vid');

    // Remove current revs from latest revs
    $latest_revs = array_diff($latest_revs, $current_revs);

    $current_revs = array_fill_keys($current_revs, 'current');
    $latest_revs = array_fill_keys($latest_revs, 'latest');
    $revisions = $current_revs + $latest_revs;

    $sandbox['total'] = count($revisions);
    $sandbox['current'] = 0;
    $sandbox['revisions'] = $revisions;
    $sandbox['#finished'] = 0;
    if ($sandbox['total'] === 0) {
      $sandbox['#finished'] = 1;
      return;
    }
  }

  $counter = 0;
  if (!empty($sandbox['revisions'])) {
    // Render using front-end theme
    $theme_manager = \Drupal::service('theme.manager');
    $active_theme = $theme_manager->getActiveTheme();
    $default_theme_name = \Drupal::config('system.theme')->get('default');
    $default_theme = \Drupal::service('theme.initialization')->getActiveThemeByName($default_theme_name);
    $theme_manager->setActiveTheme($default_theme);

    // Render using privileged user
    $root_user = \Drupal::entityTypeManager()->getStorage('user')->load(1);
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo($root_user);

    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');

    while(!empty($sandbox['revisions']) && $counter < 200) {
      $vid = key($sandbox['revisions']);
      if ($node = node_revision_load($vid)) {
        $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
        $full_output = $view_builder->view($node, 'search_index');
        $full_output = strip_tags(\Drupal::service('renderer')
          ->renderPlain($full_output));
        $fields = [
          'bundle' => $node->bundle(),
          'deleted' => 0,
          'entity_id' => $node->id(),
          'revision_id' => $vid,
          'langcode' => $lang,
          'delta' => 0,
          'field_search_text_value' => $full_output,
        ];
        $insert = Drupal::database()
          ->insert('node_revision__field_search_text');
        $insert->fields($fields);
        $insert->execute();

        if ($sandbox['revisions'][$vid] === 'current') {
          $insert = Drupal::database()->insert('node__field_search_text');
          $insert->fields($fields);
          $insert->execute();
        }
        $nodeStorage->resetCache([$node->id()]);
      }
      $counter++;
      $sandbox['current']++;
      unset($sandbox['revisions'][$vid]);
    }

    // Revert to the active theme
    $theme_manager->setActiveTheme($active_theme);

    // Switch back to original user.
    $account_switcher->switchBack();
  }

  if (empty($sandbox['revisions'])) {
    $sandbox['#finished'] = 1;
  }

  // Free up some memory.
  drupal_static_reset();
  $nodeStorage->resetCache();

  \Drupal::logger('epa_core')->notice('Processed search text for ' . $sandbox['current'] .'/'. $sandbox['total'] .' nodes.');
}
