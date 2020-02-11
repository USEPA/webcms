<?php

namespace Drupal\epa_core\Routing;

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
    $private_file_routes = ['system.files', 'system.private_file_download'];
    foreach ($private_file_routes as $route_name) {
      if ($route = $collection->get($route_name)) {
        $route->setDefault('_controller', '\Drupal\epa_core\EPAFileDownloadController::download');
      }
    }
    if ($route = $collection->get('image.style_private')) {
      $route->setDefault('_controller', '\Drupal\epa_core\EPAImageStyleDownloadController::deliver');
    }
  }

}
