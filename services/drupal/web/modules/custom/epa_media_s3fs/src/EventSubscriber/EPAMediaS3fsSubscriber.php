<?php

namespace Drupal\epa_media_s3fs\EventSubscriber;

use Drupal\Core\EventSubscriber\Fast404ExceptionHtmlSubscriber;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

/**
 * High-performance 404 exception subscriber for redirecting users to S3 when necessary.
 */
class EPAMediaS3fsSubscriber extends Fast404ExceptionHtmlSubscriber {
  
  /**
   * {@inheritdoc}
   */
  protected static function getPriority() {
    // A very high priority so that it can take precedent over Fast404ExceptionHtmlSubscriber.
    return 201;
  }

  /**
   * Handles a 404 error for HTML.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
   *   The event to process.
   */
  public function on404(GetResponseForExceptionEvent $event) {
    $request = $event->getRequest();
    $path = $request->getPathInfo();
    $new_path = preg_replace('/^\/sites\/.*\/files\/(.*)/i', 'public://$1', $path, -1, $count);

    if ($count) {
      $response = new RedirectResponse(file_create_url($new_path));
      $event->setResponse($response);
    }
  }

}
