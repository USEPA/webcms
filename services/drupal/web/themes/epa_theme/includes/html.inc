<?php

/**
 * @file
 * HTML template functions.
 */

use Drupal\Component\Utility\Html;
use Drupal\filter\Render\FilteredMarkup;

/**
 * Implements hook_preprocess_html().
 */
function epa_theme_preprocess_html(&$vars, $hook) {
  // Add x-ua-compatible meta tag.
  $vars['page']['#attached']['html_head'][] = [
    [
      '#tag' => 'meta',
      '#attributes' => [
        'http-equiv' => 'x-ua-compatible',
        'content' => 'ie=edge',
      ],
    ],
    'x_ua_compatible',
  ];
  $vars['image_path'] = '/' . $vars['directory'] . '/images';
}

/**
 * Implements hook_preprocess_maintenance_page().
 */
function epa_theme_preprocess_maintenance_page(&$vars, $hook) {
  // While markup for normal pages is split into html.html.twig and
  // page.html.twig, the markup for the maintenance page is all in the single
  // maintenance-page.html.twig template. So, to have what’s done in
  // epa_theme_preprocess_html() and epa_theme_preprocess_page() also happen
  // on the maintenance page, it has to be called here.
  epa_theme_preprocess_html($vars, $hook);
  epa_theme_preprocess_page($vars, $hook);
}

/**
 * Implements hook_preprocess_page().
 */
function epa_theme_preprocess_page(&$vars, $hook) {
}


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

function epa_theme_preprocess_media(&$vars) {
  if (isset($vars['attributes']['data-caption'])) {
    $vars['caption'] = $vars['attributes']['data-caption'];
  }
}
