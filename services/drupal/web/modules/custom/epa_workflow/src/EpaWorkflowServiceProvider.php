<?php

namespace Drupal\epa_workflow;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Alters container services.
 *
 * Note, Pascal case is required for both the filename and class name in order
 * for this service provider to be automatically registered.
 */
class EpaWorkflowServiceProvider extends ServiceProviderBase {
  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // If module "danse_content_moderation" is not installed, then remove
    // our service that decorates a service provided by the module.
    $modules = $container->getParameter('container.modules');
    if (!isset($modules['danse_content_moderation'])) {
      $container->removeDefinition('epa_workflow.content_moderation_event_subscriber');
    }
  }
}
