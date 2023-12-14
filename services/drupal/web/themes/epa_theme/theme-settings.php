<?php

/**
 * @file
 * Functions to support theme settings.
 */

use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter() for system_theme_settings.
 */
function epa_theme_form_system_theme_settings_alter(&$form, FormStateInterface $form_state) {
  // Work-around for a core bug affecting admin themes.
  // See https://www.drupal.org/docs/8/theming-drupal-8/creating-advanced-theme-settings.
  if (isset($form_id)) {
    return;
  }

  $form['breadcrumb'] = [
    '#type' => 'details',
    '#title' => t('Breadcrumb'),
    '#open' => TRUE,
  ];

  $form['breadcrumb']['include_current_page_in_breadcrumb'] = [
    '#type' => 'checkbox',
    '#title' => t('Include current page in breadcrumb'),
    '#default_value' => theme_get_setting('include_current_page_in_breadcrumb') ?? TRUE,
  ];
}
