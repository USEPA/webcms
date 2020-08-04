<?php

namespace Drupal\epa_migrations;

use Drupal\paragraphs\Entity\Paragraph;

/**
 * Helper to wrap child paragraphs in Box paragraphs.
 */
trait EpaBoxWrapperTrait {

  /**
   * Surrounds the passed-in paragraphs in a specific box style.
   *
   * @param array $children
   *   An array of Paragraphs to wrap.
   * @param string $title
   *   The title for the box.
   * @param string $box_style
   *   The box style to use.
   * @param array $image
   *   The image file id and alt text.
   *
   * @return paragraph
   *   The saved box paragraph.
   */
  public function addBoxWrapper(array $children, string $title, string $box_style, array $image = NULL) {
    // Create Box paragraph container.
    $box_paragraph = Paragraph::create(['type' => 'box']);

    if ($title) {
      // Add a space before any '<' character, then strip tags. This ensures
      // titles that were split by <br /> don't run together.
      $box_paragraph->set('field_title', strip_tags(str_replace('<', ' <', $title)));
    }

    if ($box_style) {
      $box_style_map = [
        'related' => 'related-info',
        'highlight' => 'highlight',
        'news' => 'news',
        'alert' => 'alert',
        'multi' => 'multipurpose',
        'simple' => 'multipurpose',
        'special' => 'special',
        'rss' => 'rss',
        'blog' => 'blog',
      ];

      $box_style = $box_style_map[$box_style] ?? '';
      $box_paragraph->set('field_style', $box_style);
    }

    if ($children) {
      $box_paragraph->set('field_paragraphs', $children);
    }

    if ($image) {
      $box_paragraph->set('field_image', $image);
    }

    $box_paragraph->isNew();
    $box_paragraph->save();

    return $box_paragraph;
  }

}
