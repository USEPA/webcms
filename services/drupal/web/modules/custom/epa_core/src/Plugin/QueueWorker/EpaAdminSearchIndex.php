<?php

namespace Drupal\epa_core\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * This is not relevant to search api.  This populates the search_text field
 * on revisions. Mainly just used once to get text in the field for existing
 * nodes.
 *
 * @QueueWorker(
 *   id = "epa_search_text_indexer",
 *   title = @Translation("EPA Admin Search Text Processor"),
 *   cron = {"time" = 60}
 * )
 */
class EpaAdminSearchIndex extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $lang = getenv('WEBCMS_LANG');
    // Render using front-end theme.
    $theme_manager = \Drupal::service('theme.manager');
    $active_theme = $theme_manager->getActiveTheme();
    $default_theme_name = \Drupal::config('system.theme')->get('default');
    $default_theme = \Drupal::service('theme.initialization')->getActiveThemeByName($default_theme_name);
    $theme_manager->setActiveTheme($default_theme);

    // Render using privileged user.
    $root_user = \Drupal::entityTypeManager()->getStorage('user')->load(1);
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo($root_user);

    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');

    $vid = $data['vid'];
    $type = $data['type'];
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
      $insert = \Drupal::database()
        ->upsert('node_revision__field_search_text')->key('revision_id');
      $insert->fields($fields);
      $insert->execute();

      if ($type === 'current') {
        $insert = \Drupal::database()->upsert('node__field_search_text')->key('revision_id');
        $insert->fields($fields);
        $insert->execute();
      }
      $nodeStorage->resetCache([$node->id()]);
    }

    // Revert to the active theme.
    $theme_manager->setActiveTheme($active_theme);

    // Switch back to original user.
    $account_switcher->switchBack();
  }

}
