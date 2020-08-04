<?php

/**
 * @file
 * Field template functions.
 */

/**
 * Implements hook_theme_suggestions_field_alter().
 */
function epa_theme_theme_suggestions_field_alter(&$suggestions, $variables) {
 $suggestions[] = 'field__' . $variables['element']['#entity_type'] . '__' . $variables['element']['#field_name'] . '__' . $variables['element']['#bundle'] . '__' . $variables['element']['#view_mode'];
}
