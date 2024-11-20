<?php

use Drupal\user\Entity\User;

/**
 * Iterates over all groups and sets the current "Editor in Chief" (EIC) user with the new Editor in Chief role.
 *
 * Prior to this change Editor in chief was not a Web Area role and was denoted by a field value on the Group entity.
 * This updates those users who are members of the web area, to be the new EIC role.
 */
function epa_web_areas_deploy_0002_set_eic_user_on_all_groups(&$sandbox) {
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
        \Drupal::logger('epa_web_areas')->notice('Web Area Editor in Chief user (@username) is not a member of Web Area: @name (@id). Adding them now.', ['@username' => $user->getAccountName(), '@name' => $group->label(), '@id' => $group->id()]);
        $group->addMember($user);
        $member = $group->getMember($user);
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
    else {
      \Drupal::logger('epa_web_areas')->error('Web Area @name has an Editor in Chief set to a user that does not exist in the system', ['@name' => $group->label()]);
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
