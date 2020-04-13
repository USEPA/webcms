<?php

namespace Drupal\epa_migrations;

use Drupal\migrate\Row;
use Drupal\migrate\MigrateExecutableInterface;

/**
 * Helpers to transform Paragraphs to Panes in a process plugin.
 */
trait EpaTransformParagraphsTrait {

  /**
   * Create paragraphs from pane.
   *
   * @param array $pane
   *   The pane to transform.
   * @param \Drupal\migrate\Row $row
   *   The row that is being processed.
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   The migration in which this process is being executed.
   *
   * @return array
   *   The transformed paragraph ids.
   */
  protected function transformParagraphs(array $pane, Row $row, MigrateExecutableInterface $migrate_executable) {
    $paragraph_ids = [];

    $type = $pane['type'];

    // The configuration (box style) we need for fieldable panels panes is
    // stored in the 'style' column. For all other panes we need to pull
    // it from configuration.
    $type == 'fieldable_panels_pane' ?
      $configuration = unserialize($pane['style']) :
      $configuration = unserialize($pane['configuration']);

    $pane_transformer_services = [
      'node_content' => 'epaNodeContentTransformer',
      'epa_core_html_pane' => 'epaCoreHtmlPaneTransformer',
      'epa_core_node_list_pane' => 'epaCoreListPaneTransformer',
      'epa_core_link_list_pane' => 'epaCoreListPaneTransformer',
      'fieldable_panels_pane' => 'epaFieldablePanelsPaneTransformer',
    ];

    $pane_transformer_service = $pane_transformer_services[$type] ?? NULL;

    if ($pane_transformer_service) {
      $transformed_paragraphs = $this->$pane_transformer_service->createParagraph($row, $pane, $configuration);

      if ($transformed_paragraphs) {
        // Convert transformed_paragraphs to an array if it's not already.
        $transformed_paragraphs = is_array($transformed_paragraphs) ?: [$transformed_paragraphs];
        foreach ($transformed_paragraphs as $paragraph) {
          $paragraph_ids[] = [
            'target_id' => $paragraph->id(),
            'target_revision_id' => $paragraph->getRevisionId(),
          ];
        }

      }
    }
    else {
      $migrate_executable->saveMessage("WARNING: No pane transformer was found for pane type '{$type}' with pid {$pane['pid']}. This pane is used in the '{$pane['panel']}' panel. This pane was not migrated.", 3);
    }

    return $paragraph_ids;
  }

}
