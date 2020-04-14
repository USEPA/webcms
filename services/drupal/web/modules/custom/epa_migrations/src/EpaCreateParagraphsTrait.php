<?php

namespace Drupal\epa_migrations;

use Drupal\paragraphs\Entity\Paragraph;

/**
 * Helpers to create Paragraphs.
 */
trait EpaCreateParagraphsTrait {

  /**
   * Create html paragraph.
   *
   * @param array $body_field
   *   An array with 'value' and (optional) 'format' keys.
   *
   * @return paragraph
   *   The saved html paragraph.
   */
  public function createHtmlParagraph(array $body_field) {
    $html_paragraph = Paragraph::create(['type' => 'html']);
    $html_paragraph->set('field_body', $body_field);
    $html_paragraph->isNew();
    $html_paragraph->save();

    return $html_paragraph;
  }

  /**
   * Create link_list paragraph.
   *
   * @param string $link_type
   *   Specify if the links are 'entity' links or 'uri' links.
   * @param array $links
   *   The list of links.
   *
   * @return paragraph
   *   The saved link_list paragraph.
   */
  public function createLinkListParagraph(string $link_type, array $links) {

    $list_links = [];

    // Format links based on type.
    switch ($link_type) {
      case "entity":
        foreach ($links as $item) {
          is_array($item) ?: $item = (array) $item;
          $list_links[] = [
            'uri' => 'entity:node/' . ($item['node'] ?: $item['entity']),
            'title' => '',
            'options' => [],
          ];
        }
        break;

      case "uri":
        foreach ($links as $item) {
          is_array($item) ?: $item = (array) $item;
          $list_links[] = [
            'uri' => preg_replace('@^/?node/(.*)@', 'entity:node/$1', $item['link']),
            'title' => $item['title'],
            'options' => [],
          ];
        }
        break;
    }

    // Create and save link_list paragraph.
    $link_list_paragraph = Paragraph::create(['type' => 'link_list']);
    $link_list_paragraph->set('field_links', $list_links);
    $link_list_paragraph->isNew();
    $link_list_paragraph->save();

    return $link_list_paragraph;
  }

}
