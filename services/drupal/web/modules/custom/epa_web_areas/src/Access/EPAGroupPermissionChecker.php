<?php

namespace Drupal\epa_web_areas\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\group\Access\CalculatedGroupPermissionsItemInterface;
use Drupal\group\Access\ChainGroupPermissionCalculatorInterface;
use Drupal\group\Access\GroupPermissionChecker;
use Drupal\group\Entity\GroupInterface;

/**
 * Extend GroupPermissionChecker.
 */
class EPAGroupPermissionChecker extends GroupPermissionChecker {

  /**
   * Original service object.
   *
   * @var \Drupal\group\Access\GroupPermissionChecker
   */
  protected $groupPermissionChecker;

  /**
   * Constructs a GroupPermissionChecker object.
   *
   * @param \Drupal\group\Access\GroupPermissionChecker $group_permission_checker
   *   The original group permission checker service.
   * @param \Drupal\group\Access\ChainGroupPermissionCalculatorInterface $permission_calculator
   *   The group permission calculator.
   */
  public function __construct(GroupPermissionChecker $group_permission_checker, ChainGroupPermissionCalculatorInterface $permission_calculator) {
    $this->groupPermissionChecker = $group_permission_checker;
    parent::__construct($permission_calculator);
  }

  /**
   * {@inheritdoc}
   */
  public function hasPermissionInGroup($permission, AccountInterface $account, GroupInterface $group) {

    $calculated_permissions = $this->groupPermissionCalculator->calculatePermissions($account);

    // If the user has member permissions for this group, check those, otherwise
    // we need to check the group type permissions instead, i.e.: the ones for
    // anonymous or outsider audiences.
    $item = $calculated_permissions->getItem(CalculatedGroupPermissionsItemInterface::SCOPE_GROUP, $group->id());
    if ($item === FALSE) {
      $item = $calculated_permissions->getItem(CalculatedGroupPermissionsItemInterface::SCOPE_GROUP_TYPE, $group->bundle());
    }

    return $item->hasPermission($permission);
  }

}
