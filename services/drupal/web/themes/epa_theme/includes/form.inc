<?php

/**
 * @file
 * Form template functions.
 */

/**
 * Implements template_preprocess_input().
 */
function epa_theme_preprocess_input(&$vars) {
  $vars['required'] = isset($vars['element']['#required']) ? $vars['element']['#required'] : NULL;
  $vars['type'] = isset($vars['element']['#attributes']['type']) ? $vars['element']['#attributes']['type'] : NULL;
  $vars['dropbutton'] = isset($vars['element']['#dropbutton']) ? $vars['element']['#dropbutton'] : NULL;
}

/**
 * Implements template_preprocess_fieldset().
 */
function epa_theme_preprocess_fieldset(&$vars) {
  $vars['type'] = isset($vars['element']['#type']) ? $vars['element']['#type'] : NULL;
}

/**
 * Implements template_preprocess_form().
 */
function epa_theme_preprocess_form(&$vars) {
  if ($vars['element']['#form_id'] == 'views_exposed_form') {
    $vars['attributes']['class'][] = 'margin-y-3';
  }
}

/**
 * Implements HOOK_form_FORM_ID_alter().
 */
function epa_theme_form_views_exposed_form_alter(&$form, &$form_state, $form_id) {
  // Switch template used for actions wrapper.
  $form['actions']['#theme_wrappers'] = array('container__no_wrapper');
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function epa_theme_theme_suggestions_form_alter(array &$suggestions, array $variables) {
  $form_id = $variables['element']['#form_id'];
  $suggestions[] = 'form__' . $form_id;
}
