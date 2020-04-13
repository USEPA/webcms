<?php

namespace Drupal\epa_migrations;

/**
 * Create an HTML Paragraph from a node_content pane.
 */
class EpaNodeContentPaneToParagraph extends EpaPaneToParagraph {

  /**
   * {@inheritDoc}
   */
  public function createParagraph($row, $record, $configuration) {
    $body_field = $row->get('body');
    return $this->createHtmlParagraph($body_field);
  }

}
