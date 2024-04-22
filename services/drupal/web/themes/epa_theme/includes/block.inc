<?php

/**
 * @file
 * Provides the necessary hooks for the block theme suggestions.
 */

use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Link;

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

/**
 * Implements hook_preprocess_HOOK().
 *
 * Load up the group short name and use it as the label for the menu block. Also
 * set the block title to be a link to the web area's homepage node.
 */
function epa_theme_preprocess_block__webareamenu(&$variables) {
  // Ensure we have a group ID and items in the menu before proceeding.
  if (
    !empty($variables['elements']['#contextual_links']['group_menu']['route_parameters']['group']) &&
    !empty($variables['content']['#items'])
  ) {
    $group_id = $variables['elements']['#contextual_links']['group_menu']['route_parameters']['group'];
    /** @var \Drupal\group\Entity\GroupInterface $group */
    $group = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->load($group_id);

    // 'Title' property is labeled as 'short name'.
    $short_name = $group->get('label')->value;

    // Set the short name as the block's label.
    $variables['label'] = $short_name;
    $variables['label_display'] = 1;

    // Now get the web area's set homepage node and get the url for that to
    // use as link URL.
    /** @var \Drupal\node\NodeInterface $homepage_node */
    $homepage_node = $group->get('field_homepage')->entity;
    if (!$homepage_node) {
      return;
    }

    if ($homepage_node->isPublished()) {
      $homepage_url = $homepage_node->toUrl();
      $homepage_link = Link::fromTextAndUrl($short_name, $homepage_url);
      $variables['label'] = $homepage_link->toRenderable();
    }
    // Ensure the block caching is based on the web area's homepage as well.
    $variables['#cache']['tags'][] = 'node:' . $homepage_node->id();
  }
}
