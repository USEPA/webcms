<?php

namespace Drupal\epa_migrations\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Given a set of panes, returns paragraph entity references.
 *
 * @MigrateProcessPlugin(
 *   id = "epa_panes_to_paragraphs"
 * )
 *
 * To get an array of panes converted to paragraph entity ids for a field, do
 * the following:
 *
 * @code
 * field_paragraphs:
 *   plugin: epa_panes_to_paragraphs
 *   source: main_col_panes
 * @endcode
 */
class EpaPanesToParagraphs extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $paragraph_ids = [];

    // The value passed into this process is an array of database rows, selected
    // in the epa_node source plugin.
    foreach ($value as $pane) {
      $shown = $pane['shown'];

      if ($shown) {
        $type = $pane['type'];
        $configuration = unserialize($pane['configuration']);

        $pane_classes = [
          'node_content' => '\\Drupal\epa_migrations\EpaNodeContentPaneToParagraph',
          'epa_core_html_pane' => '\\Drupal\epa_migrations\EpaCoreHtmlPaneToParagraph',
          'epa_core_node_list_pane' => '\\Drupal\epa_migrations\EpaCoreNodeListPaneToParagraph',
        ];

        $pane_class = $pane_classes[$type];
        $pane_transformer = new $pane_class();
        $transformed_paragraph_ids = $pane_transformer->createParagraph($row, $pane, $configuration);
        if ($transformed_paragraph_ids) {
          $paragraph_ids[] = $transformed_paragraph_ids;
        }

      }

    }
    return $paragraph_ids;

  }

}
