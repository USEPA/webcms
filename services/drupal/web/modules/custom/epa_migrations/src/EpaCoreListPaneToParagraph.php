<?php

namespace Drupal\epa_migrations;

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

    $body_field = $configuration['node_list_body'] ?? $configuration['link_list_body'];

    $link_type = isset($configuration['node_field']) ? 'entity' : 'uri';
    $links = $link_type == 'entity' ? $configuration['node_field'] : $configuration['link_field'];

    // Wrap the html and link_list paragraphs in a box.
    return $this->addBoxWrapper(
      [
        $this->createHtmlParagraph($body_field),
        $this->createLinkListParagraph($link_type, $links),
      ],
      $configuration['title'] ?? '',
      $configuration['box_style'] ?? 'none'
    );

  }

}
