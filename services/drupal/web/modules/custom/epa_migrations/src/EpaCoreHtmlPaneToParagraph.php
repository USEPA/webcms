<?php

namespace Drupal\epa_migrations;

use Drupal\paragraphs\Entity\Paragraph;

/**
 * Create a Box paragraph from an epa_core_html pane.
 */
class EpaCoreHtmlPaneToParagraph extends EpaPaneToParagraph {

  /**
   * {@inheritDoc}
   */
  public function createParagraph($row, $record, $configuration) {

    // Create Box paragraph container.
    $box_paragraph = Paragraph::create(['type' => 'box']);

    if ($configuration['override_title']) {
      $box_paragraph->set('field_title', $configuration['override_title_text']);
    }

    if ($configuration['box_style'] !== 'none') {
      $box_paragraph->set('field_style', $configuration['box_style']);
    }

    // Create and save child HTML paragraph.
    $body_field = $configuration['html_body'];

    $html_paragraph = Paragraph::create(['type' => 'html']);
    $html_paragraph->set('field_body', $body_field);
    $html_paragraph->isNew();
    $html_paragraph->save();

    // Assign HTML paragraph as child of Box paragraph.
    $box_paragraph->set('field_paragraphs', [
      'target_id' => $html_paragraph->id(),
      'target_revision_id' => $html_paragraph->getRevisionId(),
    ]);

    // Save Box paragraph and return its IDs.
    $box_paragraph->isNew();
    $box_paragraph->save();

    return [
      'target_id' => $box_paragraph->id(),
      'target_revision_id' => $box_paragraph->getRevisionId(),
    ];
  }

}
