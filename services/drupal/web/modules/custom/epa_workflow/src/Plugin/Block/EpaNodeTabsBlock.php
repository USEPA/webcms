<?php

namespace Drupal\epa_workflow\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\epa_workflow\Icons;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use \Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides an epa node tabs block.
 *
 * @Block(
 *   id = "epa_node_tabs",
 *   admin_label = @Translation("EPA Node Tabs"),
 *   category = @Translation("Custom"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", required = TRUE, label = @Translation("Node"))
 *   }
 * )
 */
class EpaNodeTabsBlock extends BlockBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

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
   *
   * This block is essentially a replacement for the local_tasks block.
   * It makes use of the MenuLocalTasks Manager service to retrieve all local
   * tasks for a given route. We then create our own 'tabs' that use the tasks
   * information to build out our own 'tabs' array.
   *
   * We also are now conditionally hiding the local tasks on all node pages.
   * @see \epa_workflow_menu_local_tasks_alter()
   */
  public function build() {
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheContexts(['route']);
    // @todo: Eventually remove me. This just outputs the local tasks as we normally would.
    $build['content'] = $this->pluginManagerMenuLocalTask->getTasksBuild($this->routeMatch->getRouteName(), $cacheability);

    /** @var NodeInterface $node */
    $node = $this->getContextValue('node');

    $tabs = [];
    $tabs[] = $this->initializeViewLivePage($node);

    $tasks_build = $this->pluginManagerMenuLocalTask->getTasksBuild($this->routeMatch->getRouteName(), $cacheability);
    $page_options = $this->initializePageOptions();
    foreach ($tasks_build[0] as $task_key => $tab) {
      switch ($task_key) {
        case 'entity.node.edit_form':
          $tabs[] = $this->createTabItem(
            $this->t('Edit latest draft'),
            $tab,
            Icons::PENCIL,
            -80
          );
          break;
        case 'content_moderation.workflows:node.latest_version_tab':
          $tabs[] = $this->createTabItem(
            $this->t('View latest draft'),
            $tab,
            Icons::EYE,
            -70
          );
          break;
        case 'entity.node.version_history':
          $tabs[] = $this->createTabItem(
            $this->t('Revisions'),
            $tab,
            Icons::CYCLE,
            -60
          );
          break;
        default:
          // Any other remaining items should become children of 'Page Options' item.
          $this->addChildToPageOptions($page_options, $tab);
          break;
      }
    }

    usort($page_options['#children'], ['Drupal\Component\Utility\SortArray', 'sortByWeightProperty']);

    // Finally adds the $page_options tabs to the rest of the tabs array.
    $tabs = array_merge($tabs, [$page_options]);

    // Now sort all the tabs based on the #weight property.
    usort($tabs, ['Drupal\Component\Utility\SortArray', 'sortByWeightProperty']);

    $build['epa_node_tabs'] = [
      '#theme' => 'epa_node_tabs',
      '#tabs' => $tabs,
    ];

    return $build;
  }

  /**
   * Initializes the default 'Page Options' node tab item.
   *
   * @return array
   *   The render array for the Page Options node tab item.
   */
  private function initializePageOptions() {
    return [
      '#theme' => 'epa_node_tab_item',
      '#title' => $this->t('Page Options'),
      '#url' => new Url('<none>'),
      '#icon' => Icons::GRID,
      '#is_active' => FALSE, // @todo: this needs to be active if any of the children inside it are 'active'.
      '#weight' => -100,
      '#access' => TRUE,
    ];
  }

  /**
   * Creates an individual `epa_node_tab_item` based on menu task 'tab'
   *
   * @param $title
   *   The title to use for the tab.
   * @param $tab
   *   The menu_local_task tab being used for the data
   * @param string $icon
   *  The icon to use for the tab item
   * @param $weight
   *   The weight to set for the tab (lower = higher in the list).
   * @return array
   *   The epa_node_tab_item render array.
   */
  private function createTabItem(string $title, array $tab, string $icon, int $weight): array {
    return [
      '#theme' => 'epa_node_tab_item',
      '#title' => $this->t($title),
      '#url' => $tab['#link']['url'],
      '#icon' => $icon,
      '#is_active' => $tab['#active'] ?? FALSE,
      '#weight' => $weight,
      '#access' => $tab['#access'],
    ];
  }

  /**
   * Adds local task 'tab' information to 'Page Options' item as children.
   *
   * @param array $page_options
   *   The 'Page Options' render array.
   * @param mixed $tab
   *   The local task tab to add.
   */
  private function addChildToPageOptions(array &$page_options, mixed $tab) {
    $page_options['#children'][] = [
      '#theme' => 'epa_node_tab_item',
      '#title' => $tab['#link']['title'],
      '#url' => $tab['#link']['url'],
      '#is_active' => $tab['#active'] ?? FALSE,
      '#access' => $tab['#access'],
      '#weight' => $tab['#weight'],
    ];
  }


  /**
   * Initializes the "View Live Page" node tab.
   *
   * @param NodeInterface $node
   *   The node in question.
   * @return array
   *   The render array for an epa_node_tab_item
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  private function initializeViewLivePage(NodeInterface $node) {
    return [
      '#theme' => 'epa_node_tab_item',
      '#title' => $this->t('View Live Page'),
      '#url' => ($node->isDefaultRevision() && $node->isPublished()) ? $node->toUrl() : new Url('<none>'),
      '#icon' => 'live',
      '#is_active' => FALSE, // @todo: Determine active route
      '#weight' => -90,
      '#access' => TRUE,
    ];
  }

}
