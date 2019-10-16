<?php

namespace Drupal\epa_web_areas;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeAccessControlHandler;

/**
 * Extends access control handler for the node entity type.
 */
class WebAreaAccessControlHandler extends NodeAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function createAccess($entity_bundle = NULL, AccountInterface $account = NULL, array $context = [], $return_as_object = FALSE) {
    $account = $this->prepareUser($account);

    // Always deny access to create web_area nodes.
    if ($entity_bundle == 'web_area') {
      $result = AccessResult::forbidden()->cachePerPermissions();
      return $return_as_object ? $result : $result->isAllowed();
    }

    $result = parent::createAccess($entity_bundle, $account, $context, TRUE)->cachePerPermissions();
    return $return_as_object ? $result : $result->isAllowed();
  }

}
