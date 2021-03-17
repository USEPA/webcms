<?php

namespace Drupal\epa_media\EventSubscriber;

use Drupal\Core\EventSubscriber\DefaultExceptionHtmlSubscriber;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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
  public function on403(ExceptionEvent $event) {
    $request = $event->getRequest();
    $attributes = $request->attributes;
    // If a private file is throwing a 403, then obscure the file's existence
    // by instead throwing a 404.
    $restricted_routes = [
      'system.files',
      'system.private_file_download',
      'image.style_private',
    ];
    if ($attributes->get('scheme') == 'private' && in_array($attributes->get('_route'), $restricted_routes)) {
      $event->setThrowable(new NotFoundHttpException());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onException(ExceptionEvent $event) {
    // Only handle 403 exceptions.
    $exception = $event->getThrowable();
    if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 403) {
      parent::onException($event);
    }
  }

}
