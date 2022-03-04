<?php

namespace Drupal\epa_web_areas\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\Cache;
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

      $eic = !$group->field_editor_in_chief->isEmpty() ? $group->field_editor_in_chief->entity->toLink()->toString() : "none";

      $elements[$delta] = [
        '#markup' => '<div>'. $group->toLink()->toString() .' | <div class="field__label is-inline">Editor in Chief</div><div class="field__content">'. $eic .'</div> | <div class="field__label is-inline">Roles</div><div class="field__content">'. implode(', ', $role_names) .'</div></div>',
        '#cache' => ['tags' => Cache::mergeTags($entity->getCacheTags(),$group->getCacheTags())],
      ];
    }

    return $elements;
  }

}
