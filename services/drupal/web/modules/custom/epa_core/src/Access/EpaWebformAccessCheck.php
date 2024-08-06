<?php

namespace Drupal\epa_core\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupContent;
use Drupal\group\GroupMembershipLoader;
use Drupal\node\Entity\Node;

/**
 * Custom EpaWebformAccessCheck
 *
 * As part of EPAD8-2503 we need to prevent users from being able to view
 * webform submission routes if they do not belong to said web area the form
 * belongs to.
 * @link https://forumone.atlassian.net/browse/EPAD8-2503
 */
class EpaWebformAccessCheck implements AccessInterface {

  /**
   * The group membership loader service.
   *
   * @var \Drupal\group\GroupMembershipLoader
   */
  protected $groupMembershipLoader;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * EpaWebformAccessCheck constructor.
   *
   * @param \Drupal\group\GroupMembershipLoader $group_membership_loader
   */
  public function __construct(GroupMembershipLoader $group_membership_loader, EntityTypeManagerInterface $entity_type_manager) {
    $this->groupMembershipLoader = $group_membership_loader;
    $this->entityTypeManager = $entity_type_manager;
  }

  public function access(RouteMatchInterface $route_match, AccountInterface $account) {
    // Allow access if the user is an administrator or system_webmaster.
    if ($account->hasRole('administrator') || $account->hasRole('system_webmaster')) {
      return AccessResult::allowed();
    }

    // User must belong to a group if they don't have the above roles
//    /** @var \Drupal\group\GroupMembershipLoader $group_membership_service */
//    $group_membership_service = \Drupal::service('group.membership_loader');
    $group_memberships = $this->groupMembershipLoader->loadByUser($account);

    if (empty($group_memberships)) {
      return AccessResultForbidden::forbidden('A user must belong to a group to view submissions.');
    }

    // Collect group IDs from user's memberships.
    $group_ids = array_map(fn($membership) => $membership->getGroup()->id(), $group_memberships);

    // Webforms themselves aren't tied to a Web Area, but they are referenced
    // by a "Form" node. The "Form" node does belong to a Web Area.
    // Given the webform ID search for a matching node that references the webform.
    // If we find one, compare the node's group to see if the user belongs
    // to same group.
    /** @var \Drupal\webform\Entity\Webform $webform */
    $webform = $route_match->getParameter("webform");
    $webform_id = $webform->id();

    // Add webform nodes ("form") have a "webform" field that contains the reference
    // to the webform. Query to see if we can find the
    $found = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck()
      ->condition('webform', $webform_id)
      ->execute();

    // Every webform should be associated with a node. If not then don't proceed.
    if (!$found) {
      return AccessResult::forbidden();
    }

    $id = reset($found);

    // Load the node and its group content.
    $node = Node::load($id);
    /** @var \Drupal\group\Entity\GroupContent $gc */
    $gc = GroupContent::loadByEntity($node);

    if (empty($gc)) {
      return AccessResult::forbidden("Cannot view content that does not belong to a web area.");
    }

    $group_content = reset($gc);

    if (!$group_content || !in_array($group_content->getGroup()->id(), $group_ids)) {
      return AccessResultForbidden::forbidden('User does not belong to the same group as this webform.');
    }

    return AccessResult::allowed();
  }

}
