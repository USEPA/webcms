<?php

/**
 * @file
 * HTML template functions.
 */

/**
 * Implements hook_preprocess().
 */
function epa_theme_preprocess(&$vars) {
  if (!empty($vars['directory'])) {
    $vars['epa_theme_image_path'] = '/' . $vars['directory'] . '/images/';
  }
}


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

  // Add wide template body class.
  if ($node = \Drupal::request()->attributes->get('node')) {
    if ($node->hasField('field_wide_template') && $node->field_wide_template->value) {
      $vars['attributes']['class'][] = 'has-wide-template';
    }

    if ($node->hasField('field_page_head') && $node->field_page_head->value) {
      $vars['page_js'] = $node->field_page_head->value;
    }
  }
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
