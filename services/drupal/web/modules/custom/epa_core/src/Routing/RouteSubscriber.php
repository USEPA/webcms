<?php

namespace Drupal\epa_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscribing an event.
 */
class RouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection) {
    $route = $collection->get('entity.webform.results_submissions');
    if ($route) {
      $route->setRequirement('_epa_webform_access_check', TRUE);
    }

    // See more explanation in Controller override as to why.
    if ($route = $collection->get('layout_paragraphs.builder.choose_component')) {
      $route->setDefault('_controller', '\Drupal\epa_core\Controller\EpaChooseComponentController::list');
    }
  }

}
