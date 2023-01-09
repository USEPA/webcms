<?php

namespace Drupal\epa_wysiwyg\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * EPA WYSIWYG event subscriber.
 */
class EpaWysiwygRouteSubscriber extends RouteSubscriberBase {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('ckeditor5.media_entity_metadata')) {
      $route->setDefault('_controller', '\Drupal\epa_wysiwyg\Controller\EpaWysiwygCKEditor5MediaController::mediaEntityMetadata');
    }
  }

}
