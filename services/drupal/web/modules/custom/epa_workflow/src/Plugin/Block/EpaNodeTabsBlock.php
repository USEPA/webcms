<?php

namespace Drupal\epa_workflow\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use \Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides an epa node tabs block.
 *
 * @Block(
 *   id = "epa_node_tabs",
 *   admin_label = @Translation("EPA Node Tabs"),
 *   category = @Translation("Custom")
 * )
 */
class EpaNodeTabsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The plugin.manager.menu.local_task service.
   *
   * @var \Drupal\Core\Menu\LocalTaskManager
   */
  protected $pluginManagerMenuLocalTask;

  /**
   * The current_route_match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new EpaNodeTabsBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Menu\LocalTaskManager $plugin_manager_menu_local_task
   *   The plugin.manager.menu.local_task service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CacheableDependencyInterface $plugin_manager_menu_local_task, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->pluginManagerMenuLocalTask = $plugin_manager_menu_local_task;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.menu.local_task'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    // Check if we are on a node first
    if (!$this->routeMatch->getParameter('node')) {
      // Not on a node, exit early.
      return [];
    }
        
    $condition = $account->hasPermission('use_epa_node_tabs');
    return AccessResult::allowedIf($condition);
    
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheContexts(['route']);
    $build['content'] = $this->pluginManagerMenuLocalTask->getTasksBuild($this->routeMatch->getRouteName(), $cacheability);
    return $build;
  }

}
