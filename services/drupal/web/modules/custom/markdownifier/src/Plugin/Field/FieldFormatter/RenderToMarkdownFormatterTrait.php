<?php
namespace Drupal\markdownifier\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Element;
use Drupal\markdownifier\MarkdownifierHelper;

trait RenderToMarkdownFormatterTrait {
  /**
   * {@inheritdoc}
   */
  public function view(FieldItemListInterface $items, $langcode = NULL) {
    $elements = parent::view($items, $langcode);
    $elements['#post_render'][] = [MarkdownifierHelper::class, 'postRender'];

    // This seems to be needed in the views field rendering context.  The
    // surrounding wrapper is not ever rendered, so we have to add the
    // processing to each child.
    foreach (Element::children($elements) as $key => $child) {
      $elements[$key]['#post_render'][] = [MarkdownifierHelper::class, 'postRender'];
    }
    return $elements;
  }
}
