<?php

namespace Drupal\epa_migrations\Plugin\migrate\destination;

use Drupal\migrate\Row;
use Drupal\group\Entity\Group;
use Drupal\user\Plugin\migrate\destination\EntityUser;
use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * The EPA user destination plugin that assigns group membership.
 *
 * @MigrateDestination(
 *   id = "epa_user_group_membership"
 * )
 */
class EpaUserGroupMembership extends EntityUser {

  /**
   * An associative array of group and role ids.
   *
   * @var array
   */
  protected $groupsWithRoles;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    $plugin_id = 'entity:user';
    $entity_type = static::getEntityTypeId($plugin_id);
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('entity_type.manager')->getStorage($entity_type),
      array_keys($container->get('entity_type.bundle.info')->getBundleInfo($entity_type)),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('password')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    $this->groupsWithRoles = $row->getSourceProperty('groups_with_roles');

    return parent::import($row, $old_destination_id_values);
  }

  /**
   * {@inheritdoc}
   */
  protected function save(ContentEntityInterface $entity, array $old_destination_id_values = []) {
    // Do not overwrite the root account password.
    if ($entity->id() != 1) {
      // Set the pre_hashed password so that the PasswordItem field does not hash
      // already hashed passwords. If the md5_passwords configuration option is
      // set we need to rehash the password and prefix with a U.
      // @see \Drupal\Core\Field\Plugin\Field\FieldType\PasswordItem::preSave()
      $entity->pass->pre_hashed = TRUE;
      if (isset($this->configuration['md5_passwords'])) {
        $entity->pass->value = 'U' . $this->password->hash($entity->pass->value);
      }
    }

    $entity->save();

    if ($this->groupsWithRoles) {
      foreach ($this->groupsWithRoles as $gid => $roles) {

        $d8_roles = array_map(function ($role) {
          $role_map = [
            1 => 'web_area-outsider',
            3 => 'web_area-member',
            5 => 'web_area-administrator',
            7 => 'web_area-approver',
            9 => 'web_area-editor',
          ];

          return $role_map[$role];
        }, $roles);

        $d8_roles ? $values = ['group_roles' => $d8_roles] : $values = [];

        $group = Group::load($gid);

        if ($group) {
          $group->addMember($entity, $values);
        }

      }
    }

    // Create authmap entry for everyone but superuser.
    if ($entity->id() != 1) {
      $external_auth = \Drupal::service('externalauth.externalauth');
      $external_auth->linkExistingAccount($entity->name->value, 'samlauth', $entity);
    }

    return [$entity->id()];
  }

}
