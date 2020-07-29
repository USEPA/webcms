<?php

/**
 * @file
 * Facet template functions.
 */

/**
 * Implements facets_preprocess_facets_item_list().
 */
function epa_theme_preprocess_facets_item_list(&$vars) {
  $vars['facet_id'] = $vars['facet']->id();
  $vars['facet_name'] = $vars['facet']->getName();
  $vars['facet_is_expanded'] = false;

  // Expand if facet has any active items.
  if (!empty($vars['facet']->getActiveItems())) {
    $vars['facet_is_expanded'] = true;
  }

  // Add class to facet links.
  foreach ($vars['items'] as &$item) {
    $item['value']['#url']->setOption('attributes', array(
      'class' => array('facet'),
      'rel' => 'no-follow',
    ));
  }
}

/**
 * Implements facets_preprocess_facets_summary_item_list().
 */
function epa_theme_preprocess_facets_summary_item_list(&$vars) {
  // Set reset link url.
  if (isset($vars['items'][0]['value']['#title']['#facet'])) {
    switch ($vars['items'][0]['value']['#title']['#facet']->id()) {
      case 'faqs_topic':
      case 'faqs_webarea':
        $vars['reset_url'] = '/faqs/search';
        break;

      case 'news_releases_geographic_locations':
      case 'news_releases_press_office':
      case 'news_releases_subjects':
      case 'news_releases_year':
        $vars['reset_url'] = '/newsreleases/search';
        break;

      case 'public_notices_comments_due_date':
      case 'public_notices_location_of_prop_action':
      case 'public_notices_program_or_statute':
      case 'public_notices_proposed_actions':
        $vars['reset_url'] = '/publicnotices/notices-search';
        break;
    }
  }

  // Add class to item links.
  foreach ($vars['items'] as &$item) {
    $item['value']['#url']->setOption('attributes', array(
      'class' => array('filters__link'),
      'rel' => 'no-follow',
    ));
  }
}
