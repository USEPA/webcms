<?php

/**
 * @file
 * Field template functions.
 */

function epa_theme_theme_suggestions_field_alter(&$suggestions, $variables) {
 $suggestions[] = 'field__node__' . $variables['element']['#field_name'] . '__' . $variables['element']['#bundle'] . '__' . $variables['element']['#view_mode'];
}
