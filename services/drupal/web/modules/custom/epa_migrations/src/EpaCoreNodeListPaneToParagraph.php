<?php

namespace Drupal\epa_migrations;

use Drupal\paragraphs\Entity\Paragraph;

/**
 * Create a Box paragraph from an epa_core_node_list pane.
 */
class EpaCoreNodeListPaneToParagraph extends EpaPaneToParagraph {

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
    $body_field = $configuration['node_list_body'];

    $html_paragraph = Paragraph::create(['type' => 'html']);
    $html_paragraph->set('field_body', $body_field);
    $html_paragraph->isNew();
    $html_paragraph->save();

    // Format list of links as 'entity:node/${nid}.
    $node_list = $configuration['node_field'];
    $node_list_links = [];
    foreach ($node_list as $item) {
      $node_list_links[] = [
        'uri' => 'entity:node/' . $item['node'],
        'title' => '',
        'options' => [],
      ];
    }

    // Create and save child link_list paragraph.
    $link_list_paragraph = Paragraph::create(['type' => 'link_list']);
    $link_list_paragraph->set('field_links', $node_list_links);
    $link_list_paragraph->isNew();
    $link_list_paragraph->save();

    // Assemble IDs of html and link_list paragraphs then assign them to box.
    $child_paragraph_ids = [
      [
        'target_id' => $html_paragraph->id(),
        'target_revision_id' => $html_paragraph->getRevisionId(),
      ],
      [
        'target_id' => $link_list_paragraph->id(),
        'target_revision_id' => $link_list_paragraph->getRevisionId(),
      ],
    ];

    $box_paragraph->set('field_paragraphs', $child_paragraph_ids);

    // Save Box paragraph and return its IDs.
    $box_paragraph->isNew();
    $box_paragraph->save();

    return [
      'target_id' => $box_paragraph->id(),
      'target_revision_id' => $box_paragraph->getRevisionId(),
    ];
  }
}
