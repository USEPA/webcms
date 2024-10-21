<?php

/**
 * @file
 */

use Drupal\Component\Utility\Html;

/**
 *
 */
function epa_theme_menu_active_item(&$items) {
  $current_path = \Drupal::request()->getRequestUri();
  foreach ($items as $key => $item) {
    // If path is current path, set active to li.
    if ($item['url']->toString() == $current_path) {
      // Add active link.
      $items[$key]['current'] = TRUE;
    }
    if (!empty($items[$key]['below'])) {
      epa_theme_menu_active_item($items[$key]['below']);
    }
  }
}

/**
 * Implements theme_preprocess_links__HOOK()
 *
 * @param $variables
 */
function epa_theme_preprocess_links__dropbutton(&$variables) {
  if (isset($variables['links'])) {
    foreach ($variables['links'] as $key => $item) {
      $variables['links'][$key]['link']['#attributes']['class'][] = 'button';
      $variables['links'][$key]['link']['#attributes']['class'][] = 'dropbutton__button';
    }
  }
}


/**
 * Implements theme_preprocess_menu()
 *
 * @param $vars
 */
function epa_theme_preprocess_menu(&$vars) {
  if ($vars['theme_hook_original'] === 'menu__group_menu' || $vars['theme_hook_original'] === 'menu__group_menu__web_area_menu') {
    $vars['attributes']['class'][] = 'menu--sidenav';
  }
  epa_theme_menu_active_item($vars['items']);
}

/**
 * Implements theme_preprocess_menu_local_tasks()
 *
 * @param $variables
 */
function epa_theme_preprocess_menu_local_tasks(&$variables) {
  foreach (['primary', 'secondary'] as $type) {
    $tabs = [];

    // Sort the tabs by #weight.
    uasort($variables[$type], ['Drupal\Component\Utility\SortArray', 'sortByWeightProperty']);

    foreach (array_keys($variables[$type]) as $key) {
      // Add the tab to a new array.
      $tabs[$key] = [
        'active' => $variables[$type][$key]['#active'],
        'url' => $variables[$type][$key]['#link']['url']->toString(),
        'text' => Html::escape($variables[$type][$key]['#link']['title']),
      ];

      // Check if the tab should be shown by rendering the original.
      $link = \Drupal::service('renderer')->render($variables[$type][$key]);
      if (empty((string) $link)) {
        unset($tabs[$key]);
      }
    }

    // Overwrite the original tabs data.
    $variables[$type] = $tabs;
  }
}

/**
 * Implements hook_preprocess_breadcrumb().
 */
function epa_theme_preprocess_breadcrumb(array &$variables) {
  if ($variables['breadcrumb']) {
    /** @var \Drupal\Core\Render\Renderer $renderer */
    $renderer = \Drupal::service('renderer');
    $variables['include_current_page']
      = theme_get_setting('include_current_page_in_breadcrumb', 'epa_theme') ?? TRUE;
    $themeConfig = \Drupal::config('epa_theme.settings');
    $renderer->addCacheableDependency($variables, $themeConfig);

    if ($variables['include_current_page']) {
      $request = \Drupal::request();
      $route_match = \Drupal::routeMatch();
      $variables['#cache']['contexts'][] = 'route';
      $page_title = \Drupal::service('title_resolver')->getTitle($request, $route_match->getRouteObject());

      if (!empty($page_title)) {
        $variables['page_title'] = $page_title;
        $variables['breadcrumb'][] = [
          'text' => $page_title,
        ];
      }
    }
  }
}
