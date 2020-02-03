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
    $route_names = ['system.files', 'system.private_file_download'];
    foreach ($route_names as $route_name) {
      if ($route = $collection->get($route_name)) {
        $route->setDefault('_controller', '\Drupal\epa_core\EPAFileDownloadController::download');
      }
    }
  }

}
