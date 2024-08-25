<?php

namespace Drupal\epa_media_s3fs\EventSubscriber;

use Drupal\Core\EventSubscriber\Fast404ExceptionHtmlSubscriber;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * High-performance 404 exception subscriber to redirect users to S3 as needed.
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
  public function on404(ExceptionEvent $event) {
    $request = $event->getRequest();
    $path = $request->getPathInfo();
    $new_path = preg_replace('/^\/sites\/.*\/files\/(.*)/i', 'public://$1', $path, -1, $count);

    if ($count) {
      $file_path = \Drupal::service('file_url_generator')->generateAbsoluteString($new_path);
      $response = new TrustedRedirectResponse($file_path, 302);
      $event->setResponse($response);
    }
  }

}
