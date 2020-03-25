<?php

namespace Drupal\epa_migrations;

/**
 * Create an HTML Paragraph from a node_content pane.
 */
class EpaPaneToParagraph implements EpaPaneToParagraphInterface {

  /**
   * {@inheritDoc}
   */
  public function createParagraph($row, $record, $configuration) {
    // Child classes will need to implement the transformation logic.
    return NULL;
  }

}
