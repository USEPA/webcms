<?php
use Drupal\Core\Url;

/**
 * @file
 * Facet template functions.
 */

/**
 * Implements template_preprocess_facets_summary_item_list().
 */
function epa_theme_preprocess_facets_summary_item_list(&$vars) {
  // Set reset link url.
  if (isset($vars['items'][0]['value']['#title']['#facet'])) {
    // if ($vars['items'][0]['value']['#title']['#facet']->id() == 'faqs_webarea' || $vars['items'][0]['value']['#title']['#facet']->id() == 'faqs_topic') {
    //   $vars['reset_url'] = '/faqs/search';
    // }

    switch ($vars['items'][0]['value']['#title']['#facet']->id()) {
      case 'faqs_topic':
      case 'faqs_webarea':
        $vars['reset_url'] = '/faqs/search';
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
