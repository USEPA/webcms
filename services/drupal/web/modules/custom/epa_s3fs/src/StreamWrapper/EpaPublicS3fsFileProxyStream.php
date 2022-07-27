<?php

namespace Drupal\epa_s3fs\StreamWrapper;


use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\StreamWrapper\StreamWrapperManager;

/**
 * Defines a Drupal stream wrapper class for use with public scheme.
 *
 * Provides an external Url to be able to use File Proxy to download the files
 * and then upload to S3.
 */
class EpaPublicS3fsFileProxyStream extends EpaPublicS3fsStream {

  /**
   * {@inheritdoc}
   */
  public function getExternalUrl() {
    $uri = $this->getUri();
    if (
      file_exists($uri)
      ||  !in_array(StreamWrapperManager::getScheme($this->uri), ['public', 's3'])
    ) {
      return parent::getExternalUrl();
    }
    else {
      $s3_key = str_replace('\\', '/', \Drupal::service('stream_wrapper_manager')->getTarget($uri));
      $path_parts = explode('/', $s3_key);
      array_unshift($path_parts, 's3fs_to_s3', 'files');
      $path = implode('/', $path_parts);
      return $GLOBALS['base_url'] . '/' . UrlHelper::encodePath($path);
    }
  }
}
