<?php

namespace Drupal\epa_web_areas\Routing;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a route subscriber.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Always deny access to node/add to force group relationship.
    $route_names = ['node.add', 'node.add_page'];
    foreach ($route_names as $route_name) {
      if ($route = $collection->get($route_name)) {
        $route->setRequirement('_access', 'FALSE');
      }
    }

    // Node revision routes parameter {node} is not converted into its entity.
    // @see https://www.drupal.org/node/2730631
    // @todo Remove this alter once https://www.drupal.org/node/2730631 is in.
    if ($this->moduleHandler->moduleExists('node')) {
      $node_revision_routes = [
        'entity.node.version_history',
        'entity.node.revision',
        'node.revision_revert_confirm',
        'node.revision_revert_translation_confirm',
        'node.revision_delete_confirm',
      ];
      foreach ($node_revision_routes as $route) {
        $parameters = $collection->get($route)->getOption('parameters');
        $parameters = $parameters ?: [];
        $parameters['node']['type'] = 'entity:node';
        $collection->get($route)->setOption('parameters', $parameters);
      }
    }

    // Ensure Group admin pages use the admin theme
    $route_names = [
      'entity.group.canonical',
      'view.group_nodes.page_1',
      'view.group_media.page_1',
      'view.group_members.page_1',
      'view.group_publishers.page_1',
      'entity.group.menu',
    ];
    foreach ($route_names as $route_name) {
      if ($route = $collection->get($route_name)) {
        $route->addOptions(['_admin_route' => TRUE]);
      }
    }
  }

}
