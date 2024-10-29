<?php

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\group_content_menu\GroupContentMenuInterface;
use Drupal\user\Entity\User;

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

/**
 * Iterates over all groups and sets the current "Editor in Chief" (EIC) user with the new Editor in Chief role.
 *
 * Prior to this change Editor in chief was not a Web Area role and was denoted by a field value on the Group entity.
 * This updates those users who are members of the web area, to be the new EIC role.
 */
function epa_web_areas_post_update_0002_set_eic_user_on_all_groups(&$sandbox) {
  if (!isset($sandbox['total'])) {
    $storage = \Drupal::entityTypeManager()->getStorage('group');
    $all_gids = $storage->getQuery()->accessCheck(FALSE)->execute();
    $sandbox['total'] = count($all_gids);
    $sandbox['current'] = 0;
    $sandbox['batch_size'] = 50;
  }

  $role_id = 'web_area-editor_in_chief';

  // Double check role exists for validation.
  $role_exists = \Drupal::service('entity_type.manager')
    ->getStorage('group_role')
    ->load($role_id);

  if (!$role_exists) {
    \Drupal::logger('epa_web_areas')->error('Group role with ID @role_id does not exist.', ['@role_id' => $role_id]);
    return;
  }

  $gids = \Drupal::entityQuery('group')
    ->range($sandbox['current'], $sandbox['batch_size'])
    ->accessCheck(FALSE)
    ->sort('id')
    ->execute();

  if (empty($gids)) {
    $sandbox['#finished'] = 1;
    return;
  }

  /** @var \Drupal\group\Entity\Group[] $groups */
  $groups = \Drupal::entityTypeManager()
    ->getStorage('group')
    ->loadMultiple($gids);

  foreach ($groups as $group) {
    if ($group->get('field_editor_in_chief')->isEmpty()) {
      \Drupal::logger('epa_web_areas')->error('Web Area does not have a Editor in Chief set: @name (@id)', ['@name' => $group->label(), '@id' => $group->id()]);
      $sandbox['current']++;
      continue;
    }

    // Get the user ID from the entity reference field.
    $editor_in_chief_user_id = $group->get('field_editor_in_chief')->target_id;
    $user = User::load($editor_in_chief_user_id);
    if ($user) {
      /** @var \Drupal\group\GroupMembership $member */
      $member = $group->getMember($user);
      if (!$member) {
        \Drupal::logger('epa_web_areas')->error('Web Area Editor in Chief user (@username) is not a member of Web Area: @name (@id)', ['@username' => $user->getAccountName(), '@name' => $group->label(), '@id' => $group->id()]);
        $sandbox['current']++;
        continue;
      }
      $member->addRole($role_id);

      // Log success for each group processed.
      \Drupal::logger('epa_web_areas')->notice('Assigned Editor in Chief role to user @username (@user_id) for Web Area @name (@group_id).', [
        '@username' => $user->getAccountName(),
        '@user_id' => $editor_in_chief_user_id,
        '@name' => $group->label(),
        '@group_id' => $group->id(),
      ]);
    }
    $sandbox['current']++;
  }


  if ($sandbox['current'] >= $sandbox['total']) {
    $sandbox['#finished'] = 1;
    \Drupal::logger('epa_web_areas')->notice('Successfully updated all Group memberships for editors in chief.');
  }
  else {
    $sandbox['#finished'] = ($sandbox['current'] / $sandbox['total']);
  }

}
