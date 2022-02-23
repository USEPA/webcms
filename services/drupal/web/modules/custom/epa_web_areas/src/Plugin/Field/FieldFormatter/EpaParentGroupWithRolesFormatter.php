<?php

namespace Drupal\epa_web_areas\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;

/**
 * Plugin implementation of the 'parent_group_label_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "epa_parent_group_with_roles_formatter",
 *   label = @Translation("Parent group with role information"),
 *   description = @Translation("Display the parent groups along with the user's roles in each group."),
 *   field_types = {
 *     "entitygroupfield"
 *   }
 * )
 */
class EpaParentGroupWithRolesFormatter extends EntityReferenceFormatterBase {

  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      $group = $entity->getGroup();
      $user = $entity->getEntity();
      $groupMembership = \Drupal::service('group.membership_loader')->load($group,$user);
      $role_names = [];

      $roles = $groupMembership->getRoles();
      foreach ($roles as $role) {
        $role_names[] = $role->label();
      }

      $elements[$delta] = [
        '#markup' => '<div>'. $group->toLink()->toString() .' ('. implode(', ', $role_names) .')</div>',
        '#cache' => ['tags' => $entity->getCacheTags()],
      ];
    }

    return $elements;
  }

}
