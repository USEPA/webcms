<?php

function epa_theme_menu_active_item(&$items) {
  $current_path = \Drupal::request()->getRequestUri();
  foreach ($items as $key => $item) {
    // if path is current path, set active to li
    if ($item['url']->toString() == $current_path) {
      // add active link
      $items[$key]['current'] = true;
    }
    if (!empty($items[$key]['below'])) {
      epa_theme_menu_active_item($items[$key]['below']);
    }
  }
}

 /**
 * Implements theme_preprocess_menu()
 *
 * @param $vars
 */
function epa_theme_preprocess_menu(&$vars) {
  if ($vars['theme_hook_original'] === 'menu__group_menu') {
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
         'text' => \Drupal\Component\Utility\Html::escape($variables[$type][$key]['#link']['title'])
       ];

       // Check if the tab should be shown by rendering the original.
       $link = \Drupal::service('renderer')->render($variables[$type][$key]);
       if (!$link) {
         unset($tabs[$key]);
       }
     }

     // Overwrite the original tabs data.
     $variables[$type] = $tabs;
   }
 }
