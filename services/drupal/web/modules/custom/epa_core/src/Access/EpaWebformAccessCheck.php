<?php

namespace Drupal\epa_core\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupContent;

class EpaWebformAccessCheck implements AccessInterface {
  public function access(RouteMatchInterface $route_match, AccountInterface $account) {
    $webform = $route_match->getParameter("webform");
    /** @var GroupContent $gc */
    $gc = GroupContent::loadByEntity($webform);
    $groups = \Drupal::service('group.membership_loader')->loadByUser();
    \Drupal::messenger()->addMessage('Hello from Access Checker!');
    return AccessResult::allowed();
  }
}
