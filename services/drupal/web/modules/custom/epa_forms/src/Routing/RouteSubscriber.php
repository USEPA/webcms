<?php

namespace Drupal\epa_forms\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Always deny access to create or delete webforms.
    $blocked_routes = ['entity.webform.add_form', 'entity.webform.delete_form'];
    foreach ($blocked_routes as $blocked_route) {
      if ($route = $collection->get($blocked_route)) {
        $route->setRequirement('_access', 'FALSE');
      }
    }
  }

}
