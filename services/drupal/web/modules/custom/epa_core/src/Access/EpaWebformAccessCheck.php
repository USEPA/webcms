<?php

namespace Drupal\epa_core\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupContent;
use Drupal\node\Entity\Node;

class EpaWebformAccessCheck implements AccessInterface {
  public function access(RouteMatchInterface $route_match, AccountInterface $account) {
    // If the user is an admin they should be able to see this route
//    if ($account->hasRole('administrator')) {
//      return AccessResult::allowed();
//    }


    // Check if the user has any groups. If they don't have any groups then they
    // are forbidden.
    /** @var \Drupal\group\GroupMembershipLoader $group_membership_service */
    $group_membership_service = \Drupal::service('group.membership_loader');
    $groups = $group_membership_service->loadByUser($account);
    if (empty($groups)) {
      return AccessResultForbidden::forbidden('A user must belong to a group to view submissions');
    }

    /** @var \Drupal\webform\Entity\Webform $webform */
    $webform = $route_match->getParameter("webform");
    $webform_id = $webform->id();
    if (str_starts_with($webform_id, 'webform')) {
      $id = explode('_', $webform_id)[1];
    }
    $node = Node::load($id);
    /** @var GroupContent $gc */
    $gc = GroupContent::loadByEntity($node);
    $gc = reset($gc);
    $gid = $gc->getGroup()->id();


    \Drupal::messenger()->addMessage('Hello from Access Checker!');
    return AccessResult::allowed();
  }
}
