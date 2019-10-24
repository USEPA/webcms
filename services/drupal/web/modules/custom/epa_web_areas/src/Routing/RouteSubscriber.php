<?php

namespace Drupal\epa_web_areas\Routing;

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
    // Always deny access to node/add to force group relationship.
    $route_names = ['node.add', 'node.add_page'];
    foreach ($route_names as $route_name) {
      if ($route = $collection->get($route_name)) {
        $route->setRequirement('_access', 'FALSE');
      }
    }
  }

}
