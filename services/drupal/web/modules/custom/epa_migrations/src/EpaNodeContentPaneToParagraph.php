<?php

namespace Drupal\epa_migrations;

use Drupal\paragraphs\Entity\Paragraph;

/**
 * Create an HTML Paragraph from a node_content pane.
 */
class EpaNodeContentPaneToParagraph extends EpaPaneToParagraph {

  /**
   * {@inheritDoc}
   */
  public function createParagraph($row, $record, $configuration) {
    $body_field = $row->get('body');

    $html_paragraph = Paragraph::create(['type' => 'html']);
    $html_paragraph->set('field_body', $body_field);
    $html_paragraph->isNew();
    $html_paragraph->save();

    return [
      'target_id' => $html_paragraph->id(),
      'target_revision_id' => $html_paragraph->getRevisionId(),
    ];
  }

}
