<?php

namespace Drupal\epa_media\EventSubscriber;

use Drupal\Core\EventSubscriber\DefaultExceptionHtmlSubscriber;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Exception subscriber for handling core custom HTML error pages.
 */
class EPAMediaExceptionHtmlSubscriber extends DefaultExceptionHtmlSubscriber {

  /**
   * {@inheritdoc}
   */
  protected static function getPriority() {
    return -20;
  }

  /**
   * {@inheritdoc}
   */
  public function on403(GetResponseForExceptionEvent $event) {
    $request = $event->getRequest();
    $attributes = $request->attributes;
    // If a private file is throwing a 403, then obscure the file's existence
    // by instead throwing a 404.
    if ($attributes->get('scheme') == 'private' && $attributes->get('_route') == 'system.files') {
      $event->setException(new NotFoundHttpException());
    }
  }

}
