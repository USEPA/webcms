<?php

namespace Drupal\epa_migrations;

use Drupal\migrate\Row;

/**
 * An interface for creating a Paragraph from Pane data.
 */
interface EpaPaneToParagraphInterface {

  /**
   * Create a Paragraph entity from a Pane DB record.
   *
   * @param \Drupal\migrate\Row $row
   *   The current row being processed.
   * @param array $record
   *   The pane record from the Drupal 7 DB.
   * @param object $configuration
   *   The unserialized configuration object from the record.
   *
   * @return array|null
   *   An array of destination ids for the Paragraph, keyed by destination id
   *   key, or NULL if the pane will not be migrated.
   */
  public function createParagraph(Row $row, array $record, object $configuration);

}
