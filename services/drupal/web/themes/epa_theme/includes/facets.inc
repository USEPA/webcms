<?php

///**
// * @file
// * Facet template functions.
// */
//
///**
// * Implements facets_preprocess_facets_item_list().
// */
//function epa_theme_preprocess_facets_item_list(&$vars) {
//  if (isset($vars['facet'])) {
//    $vars['facet_id'] = $vars['facet']->id();
//    $vars['facet_name'] = $vars['facet']->getName();
//    $vars['facet_is_expanded'] = FALSE;
//
//    // Expand if facet has any active items.
//    if (!empty($vars['facet']->getActiveItems())) {
//      $vars['facet_is_expanded'] = TRUE;
//    }
//
//    // Specifically expand the language facet by default.
//    if ($vars['facet']->id() == 'news_releases_language') {
//      $vars['facet_is_expanded'] = TRUE;
//    }
//  }
//
//  foreach ($vars['items'] as &$item) {
//    // Add class to facet links.
//    $item['value']['#url']->setOption('attributes', [
//      'class' => ['facet'],
//      'rel' => 'no-follow',
//    ]);
//
//    // format value to be html
//    $formatted_value = new \Twig\Markup( $item['value']['#title']['#value'], 'UTF-8' );
//    $item['value']['#title']['#value'] = $formatted_value;
//  }
//}
//
///**
// * Implements facets_preprocess_facets_summary_item_list().
// */
//function epa_theme_preprocess_facets_summary_item_list(&$vars) {
//  // Add class to item links.
//  foreach ($vars['items'] as $key => &$item) {
//    $item['value']['#url']->setOption('attributes', [
//      'class' => ['filters__link'],
//      'rel' => 'no-follow',
//    ]);
//
//    // Extract the facet reset link.
//    if (isset($item["value"]["#wrapper_attributes"]["class"][0]) && $item["value"]["#wrapper_attributes"]["class"][0] == 'facet-summary-item--clear') {
//      $vars['reset_url'] = $item['value']['#url']->toString();
//      unset($vars['items'][$key]);
//    }
//  }
//
//  // format value to be html
//  foreach($vars['items'] as &$item) {
//    $formatted_value = new \Twig\Markup( $item['value']['#title']['#value'], 'UTF-8' );
//    $item['value']['#title']['#value'] = $formatted_value;
//  }
//}
