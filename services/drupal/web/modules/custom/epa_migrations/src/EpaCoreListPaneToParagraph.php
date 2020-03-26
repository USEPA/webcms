<?php

namespace Drupal\epa_migrations;

use Drupal\paragraphs\Entity\Paragraph;

/**
 * Create a Box paragraph from panes that contain lists of links.
 *
 * Convert epa_core_link_list_pane and epa_core_node_link panes to link_list
 * paragraphs.
 */
class EpaCoreListPaneToParagraph extends EpaPaneToParagraph {

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
    $body_field = $configuration['node_list_body'] ?? $configuration['link_list_body'];

    $html_paragraph = Paragraph::create(['type' => 'html']);
    $html_paragraph->set('field_body', $body_field);
    $html_paragraph->isNew();
    $html_paragraph->save();

    // Get links from Pane configuration.
    $list_links = [];
    if (isset($configuration['node_field'])) {
      // Format list of links as 'entity:node/${nid}.
      $node_list = $configuration['node_field'];
      foreach ($node_list as $item) {
        $list_links[] = [
          'uri' => 'entity:node/' . $item['node'],
          'title' => '',
          'options' => [],
        ];
      }
    }
    elseif (isset($configuration['link_field'])) {
      // Pull titles and uris from links.
      $link_list = $configuration['link_field'];
      foreach ($link_list as $item) {
        $list_links[] = [
          'uri' => $item['link'],
          'title' => $item['title'],
          'options' => [],
        ];
      }
    }

    // Create and save child link_list paragraph.
    $link_list_paragraph = Paragraph::create(['type' => 'link_list']);
    $link_list_paragraph->set('field_links', $list_links);
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
