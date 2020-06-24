<?php

/**
 * @file
 * Media template functions.
 */

use Drupal\Component\Utility\Html;
use Drupal\filter\Render\FilteredMarkup;

/**
 * Implements hook_preprocess_filter_caption().
 */
function epa_theme_preprocess_filter_caption(&$vars) {
  $dom = Html::load($vars['node']);
  // Make captions available in media templates
  foreach ($dom->getElementsByTagName('drupal-media') as $node) {
    $new_node = $node->cloneNode(TRUE);
    $new_node->setAttribute('data-caption', $vars['caption']);
    $node->parentNode->replaceChild($new_node,$node);
  }
  $vars['node'] = FilteredMarkup::create(Html::serialize($dom));
}

/**
 * Implements hook_preprocess_media().
 */
function epa_theme_preprocess_media(&$vars) {
  if (isset($vars['attributes']['data-caption'])) {
    $vars['caption'] = $vars['attributes']['data-caption'];
  }
}

/**
 * Implements hook_preprocess_responsive_image().
 */
function epa_theme_preprocess_responsive_image(&$vars) {
  // We want the width attribute added in case they upload small images.
  $vars['img_element']['#attributes']['width'] = $vars['width'];

  // Add class to box images.
  if ($vars['responsive_image_style_id'] == 'responsive_box_image') {
    $vars['img_element']['#attributes']['class'][] = 'box__image';
  }
}
