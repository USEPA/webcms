<?php

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\group_content_menu\GroupContentMenuInterface;

/**
 * Disable the first link for each Web Area menu's if it links to the same homepage node as what's set on the field_homepage for the group AND if it doesn't have any children.
 */
function epa_web_areas_post_update_0001_web_area_menu_disable_home_link(&$sandbox) {
  /** @var \Drupal\group\Entity\GroupInterface[] $groups */
  $groups = \Drupal::entityTypeManager()
    ->getStorage('group')
    ->loadMultiple();

  /** @var \Drupal\path_alias\AliasManager $path_manager */
  $path_manager = \Drupal::service('path_alias.manager');

  /** @var \Drupal\menu_link_content\MenuLinkContentStorageInterface $menu_link_storage */
  $menu_link_storage = \Drupal::entityTypeManager()
    ->getStorage('menu_link_content');

  /** @var \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree */
  $menu_tree = \Drupal::service('menu.link_tree');
  $menu_parameters = new MenuTreeParameters();
  $menu_parameters->setTopLevelOnly();

  // This puts the menu tree in the right order.
  $manipulators = [
    ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
  ];

  foreach ($groups as $group) {
    // Now load any group menus based on that group. Should only be one, however
    // there's no reason they can't have more than one.
    $content_menus = group_content_menu_get_menus_per_group($group);

    // Loop over each menu, load & build the menu tree, and look at the first item.
    foreach ($content_menus as $menu) {
      $menu_name = GroupContentMenuInterface::MENU_PREFIX . $menu->id();
      $tree = $menu_tree->load($menu_name, $menu_parameters);

      if (empty($tree)) {
        \Drupal::logger('epa_web_areas_menu_cleanup')->notice("{$group->label()} ({$group->id()}) menu does not have any menu items.");
        continue;
      }

      // This is what actually puts the menu tree in the order based on the UI.
      $tree = $menu_tree->transform($tree, $manipulators);

      /** @var \Drupal\Core\Menu\MenuLinkTreeElement $first */
      if ($first = reset($tree)) {
        // If it has children log and continue on. That will manually need to be cleaned up.
        if ($first->hasChildren) {
          \Drupal::logger('epa_web_areas_menu_cleanup')->notice("{$group->label()} ({$group->id()}) menu first link has children. Skipping.");
          continue;
        }

        /** @var \Drupal\menu_link_content\Plugin\Menu\MenuLinkContent $first_link */
        $first_link = $first->link;
        /** @var \Drupal\Core\Url $first_link_url */
        $first_link_url = $first_link->getUrlObject();
        // The menu link could be external. If so, we need to get the actual path
        if ($first_link_url->isExternal()) {
          $parts = parse_url($first_link_url->toString());
          if (str_contains($parts['host'], 'epa.gov') && isset($parts['path'])) {
            // If it's an EPA link, we can just use the path.
            $first_link_path = $parts['path'];
          }
          else {
            \Drupal::logger('epa_web_areas_menu_cleanup')->notice("{$group->label()} ({$group->id()}) first menu link URL goes off domain");
            continue;
          }
        }
        else {
          $first_link_path = $first_link_url->toString();
        }

        // Get the field_homepage node from the group to use for comparing urls.
        $homepage = $group->get('field_homepage')->entity;
        if (!$homepage) {
          \Drupal::logger('epa_web_areas_menu_cleanup')->notice("{$group->label()} ({$group->id()}) does not have homepage set");
          continue;
        }
        if (!$homepage->isPublished()) {
          \Drupal::logger('epa_web_areas_menu_cleanup')->notice("{$group->label()} ({$group->id()}) homepage is not published");
          continue;
        }

        $homepage_url_path = $homepage->toUrl()->toString();

        if (str_starts_with($homepage_url_path, '/node/')) {
          $homepage_url_path = $path_manager->getAliasByPath($homepage_url_path);
        }

        if (str_starts_with($first_link_path, '/node/')) {
          $first_link_path = $path_manager->getAliasByPath($first_link_path);
        }

        // Compare if the group's field_homepage URL is going to the same place as
        // the first menu link AND if it doesn't have any children.
        if ($first_link_path == $homepage_url_path) {
          // First link is the homepage link AND it has no children. Now load the
          // actual menu link entity and set it to be disabled.
          $plugin_definition = $first_link->getPluginDefinition();
          $actual_menu_link = $menu_link_storage->load($plugin_definition['metadata']['entity_id']);
          $actual_menu_link->set('enabled', FALSE)->save();
          \Drupal::logger('epa_web_areas_menu_cleanup')->notice("Successfully disabled home link for {$group->label()} ({$group->id()}");
        }
        else {
          \Drupal::logger('epa_web_areas_menu_cleanup')->notice("{$group->label()} ({$group->id()}) menu first link does not go to homepage: \n Links compared were: $first_link_path and $homepage_url_path");
        }
      }
    }
  }
}
