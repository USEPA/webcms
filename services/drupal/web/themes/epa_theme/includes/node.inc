<?php

/**
 * @file
 * Node template functions.
 */

/**
 * Implements template_preprocess_node().
 */
function epa_theme_preprocess_node(&$vars) {
  $node = $vars['node'];
  if ($node->hasField('field_wide_template') && $node->field_wide_template->value) {
    $vars['attributes']['class'][] = 'has-wide-template';
  }
}
