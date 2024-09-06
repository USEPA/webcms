<?php

/**
 * @file
 * Form template functions.
 */

/**
 * Implements template_preprocess_input().
 */
function epa_theme_preprocess_input(&$vars) {
  $vars['required'] = $vars['element']['#required'] ?? NULL;
  $vars['type'] = $vars['element']['#attributes']['type'] ?? NULL;
  $vars['dropbutton'] = $vars['element']['#dropbutton'] ?? NULL;
}

/**
 * Implements template_preprocess_fieldset().
 */
function epa_theme_preprocess_fieldset(&$vars) {
  $vars['type'] = $vars['element']['#type'] ?? NULL;
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
 * Implements template_preprocess_form().
 */
function epa_theme_preprocess_entity_moderation_form(&$vars) {
  if (isset($vars['form']['nid'])) {
    $vars['nid']['title'] = $vars['form']['nid']['#title'];
    $vars['nid']['content'] = [
      '#markup' => $vars['form']['nid']['#markup'],
    ];
  }

  if (isset($vars['form']['revision_author'])) {
    $vars['revision_author']['title'] = $vars['form']['revision_author']['#title'];
    $vars['revision_author']['content'] = [
      '#markup' => $vars['form']['revision_author']['#markup'],
    ];
  }

  if (isset($vars['form']['review_deadline'])) {
    $vars['review_deadline']['title'] = $vars['form']['review_deadline']['#title'];
    $vars['review_deadline']['content'] = [
      '#markup' => $vars['form']['review_deadline']['#markup'],
    ];
  }

  if (isset($vars['form']['current'])) {
    $vars['current']['title'] = $vars['form']['current']['#title'];
    $vars['current']['content'] = [
      '#markup' => $vars['form']['current']['#markup'],
    ];
  }
}

/**
 * Implements HOOK_form_FORM_ID_alter().
 */
function epa_theme_form_views_exposed_form_alter(&$form, &$form_state, $form_id) {
  // Switch template used for actions wrapper.
  $form['actions']['#theme_wrappers'] = ['container__no_wrapper'];
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function epa_theme_theme_suggestions_form_alter(array &$suggestions, array $variables) {
  $form_id = $variables['element']['#form_id'];
  $suggestions[] = 'form__' . $form_id;
}
