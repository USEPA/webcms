<?php

/**
 * @file
 * Provides the necessary hooks for the block theme suggestions.
 */

use Drupal\block_content\BlockContentInterface;

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function epa_theme_theme_suggestions_block_alter(array &$suggestions, array $variables) {
  $content = $variables['elements']['content'];
  if (isset($content['#block_content']) and $content['#block_content'] instanceof BlockContentInterface) {
    $suggestions = [];
    $bundle = $content['#block_content']->bundle();
    $view_mode = $content['#view_mode'];
    $suggestions[] = 'block__' . $bundle;
    $suggestions[] = 'block__' . $view_mode;
    $suggestions[] = 'block__' . $bundle . '__' . $view_mode;
    if (!empty($variables['elements']['#id'])) {
      $suggestions[] = 'block__' . $variables['elements']['#id'];
    }

    /** @var \Drupal\fixed_block_content\FixedToContentMappingHandlerInterface $mapping_handler */
    $mapping_handler = \Drupal::entityTypeManager()->getHandler('fixed_block_content', 'mapping_handler');

    // Search the fixed block of the edited custom block.
    if ($fixed_block = $mapping_handler->getFixedBlock($content['#block_content'])) {
      $suggestions[] = 'block__fixed__' . $fixed_block->id();
    }
    if (isset($variables['elements']['#configuration']['provider'])) {
      $suggestions[] = 'block__' . $variables['elements']['#configuration']['provider'];
    }
  }
}
