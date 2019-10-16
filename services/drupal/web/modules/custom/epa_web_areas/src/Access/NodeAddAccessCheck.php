<?php

namespace Drupal\epa_web_areas\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\node\NodeTypeInterface;

/**
 * Determines access to for node add pages.
 *
 * Necessary to forbid access to route by users with admin role.
 * Has to return allowed when using multiple access checks.
 */
class NodeAddAccessCheck implements AccessInterface {

  /**
   * Checks access to the node add page for the node type.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   (optional) The node type. If not specified, access is allowed if there
   *   exists at least one node type for which the user may create a node.
   *
   * @return string
   *   A \Drupal\Core\Access\AccessInterface constant value.
   */
  public function access(NodeTypeInterface $node_type = NULL) {
    // Always deny access to 'node/add/web_area'.
    if ($node_type && $node_type->id() == 'web_area') {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

}
