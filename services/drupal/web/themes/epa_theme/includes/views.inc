<?php

/**
 * Implements views_theme_suggestions_views_view().
 */
function views_theme_suggestions_views_view(array $variables) {
  $suggestions = [];
  $view = $variables['view'];

  $suggestions[] = 'views_view__' . $view->id();
  $suggestions[] = 'views_view__' . $view->current_display;
  $suggestions[] = 'views_view__' . $view->id() . '__' . $view->current_display;

  return $suggestions;
}

/**
 * Implements views_theme_suggestions_views_view_unformatted().
 */
function views_theme_suggestions_views_view_unformatted(array $variables) {
  $suggestions = [];
  $view = $variables['view'];

  $suggestions[] = 'views_view_unformatted__' . $view->id();
  $suggestions[] = 'views_view_unformatted__' . $view->current_display;
  $suggestions[] = 'views_view_unformatted__' . $view->id() . '__' . $view->current_display;

  return $suggestions;
}

/**
 * Implements template_preprocess_views_view().
 */
function epa_theme_preprocess_views_view(&$variables) {
  $variables['path'] = $variables['view']->getRequest()->getPathInfo();
}

/**
 * Implements template_preprocess_views_view_unformatted().
 */
function epa_theme_preprocess_views_view_unformatted(&$variables) {
  $row_plugin = $variables['view']->display_handler->getPlugin('row');
  if (isset($row_plugin->options['view_mode'])) {
    $variables['view_mode'] = $row_plugin->options['view_mode'];
  }
}

/**
* Implements hook_theme_suggestions_container_alter().
*/
function epa_theme_theme_suggestions_container_alter(array &$suggestions, array $variables) {
  // A list of view names in which to exclude the container markup.
  // Included views will use container--no-wrapper.html.twig instead of container.html.twig.
  $exclude_views = [
    'dynamic_content_list',
    'dynamic_event_list',
    'dynamic_faq_list',
    'dynamic_news_release_list',
    'dynamic_public_notice_list',
    'search_faqs',
    'search_news_releases',
    'search_public_notices',
  ];
  if (isset($variables['element']['#name'])) {
    if (in_array($variables['element']['#name'], $exclude_views)) {
      $suggestions[] = 'container__no_wrapper';
    }
  }
}
