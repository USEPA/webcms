<?php

namespace Drupal\epa_inline_media\Routing;

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
    if ($route = $collection->get('editor.media_dialog')) {
      $route->setDefault('_form', '\Drupal\epa_inline_media\Form\EPAEditorMediaDialog');
    }
  }

}
