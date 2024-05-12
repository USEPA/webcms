<?php

namespace Drupal\epa_workflow\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\epa_workflow\Icons;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\node\Plugin\views\filter\Access;
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
      return AccessResultForbidden::forbidden();
    }

    return AccessResultForbidden::allowedIfHasPermission($account, 'use_epa_node_tabs');
  }

  /**
   * {@inheritdoc}
   *
   * This block is essentially a replacement for the local_tasks block.
   * It makes use of the MenuLocalTasks Manager service to retrieve all local
   * tasks for a given route. We then create our own 'tabs' that use the tasks
   * information to build out our own 'tabs' array.
   *
   * We still rely on customizations to the local tasks via hook_menu_local_tasks_alter()
   * @see \epa_workflow_menu_local_tasks_alter()
   */
  public function build() {
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheContexts(['route']);
    $cacheability->addCacheTags(['node']);

    /** @var NodeInterface $node */
    $node = $this->getContextValue('node');

    $tabs = [];
    $route_name = $this->routeMatch->getRouteName();
    // This gets all the local tasks after the hook_menu_local_tasks_alter() has been ran.
    $tasks = $this->pluginManagerMenuLocalTask->getLocalTasks($route_name);

    $page_options = $this->initializePageOptions();
    foreach ($tasks['tabs'] as $task_key => $tab) {
      switch ($task_key) {
        case 'entity.node.canonical':
          if (!$node->isPublished()) {
            $tab['#link']['url'] = new Url('<none>');
            $tab['#active'] = FALSE;
          }

          $tabs[] = $this->createTabItem(
            $this->t('View live page'),
            $tab,
            Icons::LIVE,
            -90,
          );
          break;
        case 'entity.node.edit_form':
          $tabs[] = $this->createTabItem(
            $this->t('Edit latest draft'),
            $tab,
            Icons::PENCIL,
            -80
          );
          break;
        case 'content_moderation.workflows:node.latest_version_tab':
          // The "View latest draft" tab should be enabled in the following:
          // - should link to /node/[id]/latest if latest revision doesn't match current revision
          // - should link to /node/[id] if latest revision matches current revision and current revision is NOT published
          // - Or if latest revision == current revision and current revision IS NOT published then this s.
          if ($node->isLatestRevision() && !$node->isPublished()) {
            $tab['#link']['url'] = $node->toUrl();
            $tab['#active'] = TRUE;
          }

          // Access to the latest_version tab is not always allowed based on the workflow
          // state of the node. If that's the case we need to modify it so that we
          // can still show it.
          if ($tab['#access'] instanceof AccessResultForbidden) {
            $tab['#link']['url'] = new Url('<none>');
            $tab['#access'] = new AccessResultAllowed();
          }

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
          // Any other remaining items should become children of 'Page Options'
          $this->addChildToPageOptions($page_options, $tab);
          break;
      }
    }

    // After we've built the tabs lets remove the items that the user has access to.
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
}
