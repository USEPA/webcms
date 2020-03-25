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

    $htmlParagraph = Paragraph::create(['type' => 'html']);
    $htmlParagraph->set('field_body', $body_field);
    $htmlParagraph->isNew();
    $htmlParagraph->save();

    return [
      'target_id' => $htmlParagraph->id(),
      'target_revision_id' => $htmlParagraph->getRevisionId(),
    ];
  }

}
