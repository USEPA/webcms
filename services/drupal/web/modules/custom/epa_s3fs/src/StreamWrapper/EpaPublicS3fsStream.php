<?php

namespace Drupal\epa_s3fs\StreamWrapper;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\s3fs\StreamWrapper\PublicS3fsStream;

/**
 * Defines a Drupal s3fs stream wrapper class for use with public scheme.
 *
 * Provides support for storing files on the amazon s3 file system with the
 * Drupal file interface.
 */
class EpaPublicS3fsStream extends PublicS3fsStream {

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->t('EPA Public files (s3fs)');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Differs from normal S3fs in that it does not serve files directly off the S3 domain.');
  }

  /**
   * {@inheritdoc}
   *
   * Returns a web accessible URL for the resource.
   *
   * The format of the returned URL will be different depending on how the S3
   * integration has been configured on the S3 File System admin page.
   *
   * @return string
   *   A web accessible URL for the resource.
   */
  public function getExternalUrl() {
    // In case we're on Windows, replace backslashes with forward-slashes.
    // Note that $uri is the unaltered value of the File's URI, while
    // $s3_key may be changed at various points to account for implementation
    // details on the S3 side (e.g. root_folder, s3fs-public).
    $path = str_replace('\\', '/', $this->streamWrapperManager::getTarget($this->uri));

    // When generating an image derivative URL, e.g. styles/thumbnail/blah.jpg,
    // if the file doesn't exist, provide a URL to s3fs's special version of
    // image_style_deliver(), which will create the derivative when that URL
    // gets requested.
    $path_parts = explode('/', $path);
    if ($path_parts[0] == 'styles' && substr($path, -4) != '.css') {
      if (!$this->getS3fsObject($this->uri)) {
        // The style delivery path looks like: s3/files/styles/thumbnail/...
        // And $path_parts looks like ['styles', 'thumbnail', ...],
        // so just prepend s3/files/.
        array_unshift($path_parts, 's3', 'files');
        $new_path = implode('/', $path_parts);
        return $GLOBALS['base_url'] . '/' . UrlHelper::encodePath($new_path);
      }
    }
    return PublicStream::baseUrl() . '/' . UrlHelper::encodePath($path);
  }
}
