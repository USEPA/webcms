<?php

namespace Drupal\epa_s3fs;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Defines a service provider for the EPA S3FS module.
 */
class EpaS3fsServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Replace the public stream wrapper with S3fsStream.
    if ($container->getDefinition('stream_wrapper.public')->getClass() == 'Drupal\s3fs\StreamWrapper\PublicS3fsStream') {
      $container->getDefinition('stream_wrapper.public')
        ->setClass('Drupal\epa_s3fs\StreamWrapper\EpaPublicS3fsStream');
    }
  }

}
