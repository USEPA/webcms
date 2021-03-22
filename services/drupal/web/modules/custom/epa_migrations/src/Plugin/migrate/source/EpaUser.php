<?php

namespace Drupal\epa_migrations\Plugin\migrate\source;

use Drupal\user\Plugin\migrate\source\d7\User;
use Drupal\migrate\Row;

/**
 * The 'epa_user' source plugin.
 *
 * @MigrateSource(
 *   id = "epa_user",
 *   source_module = "user"
 * )
 */
class EpaUser extends User {

  /**
   * {@inheritDoc}
   */
  protected $batchSize = 1000;

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {

    $uid = $row->getSourceProperty('uid');

    // Find the groups the user belongs to.
    $groups = $this->select('epa_og_og_membership', 'eoom')
      ->condition('eoom.entity_type', 'user')
      ->condition('eoom.etid', $uid)
      ->fields('eoom', ['gid'])
      ->execute()
      ->fetchAll();

    if ($groups) {
      // Find all the 'roles' that are assigned to this user.
      $roles = $this->select('og_users_roles', 'our')
        ->fields('our', ['rid', 'gid'])
        ->condition('our.uid', $uid)
        ->execute()
        ->fetchAll();

      $groups_with_roles = [];

      // Iterate through each group and find any applicable roles.
      foreach ($groups as $gid) {
        $gid = $gid['gid'];
        $groups_with_roles[$gid] = [];
        foreach ($roles as $role) {
          if ($role['gid'] === $gid) {
            $groups_with_roles[$gid][] = $role['rid'];
          }
        }
      }

      $row->setSourceProperty('groups_with_roles', $groups_with_roles);
    }

    return parent::prepareRow($row);
  }

}
