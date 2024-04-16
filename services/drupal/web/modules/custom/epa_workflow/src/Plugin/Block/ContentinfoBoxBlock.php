<?php

namespace Drupal\epa_workflow\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\epa_workflow\ModerationStateToColorMapTrait;
use Drupal\Core\Block\BlockManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Content Info Box block.
 *
 * @Block(
 *   id = "epa_workflow_contentinfobox",
 *   admin_label = @Translation("Content Info Box"),
 *   category = @Translation("Custom"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", required = TRUE, label = @Translation("Node"))
 *   }
 * )
 */
class ContentinfoBoxBlock extends BlockBase implements ContainerFactoryPluginInterface {
  use ModerationStateToColorMapTrait;

  const DIFF_FILTER = 'visual_inline';

  /**
   * The block plugin manager service.
   *
   * @var \Drupal\Core\Block\BlockManager
   */
  protected $blockManager;

  /**
   * The current_route_match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new ContentInfoBoxBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param $plugin_id
   *   The plugin ID.
   * @param $plugin_definition
   *   The plugin implementation defintion.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Drupal\Core\Block\BlockManager $block_manager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, BlockManager $block_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->blockManager = $block_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('plugin.manager.block')
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

    // @TODO: Add permission for this.
    return AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getContextValue('node');
    // @TODO: Get the current node state.
    $node_state = 'published_needs_review';
    // @TODO: Determine this stuff
    $compare_link = $this->buildCompareLink(123, 15, 16);

    $build['content'] = [
      '#theme' => 'epa_content_info_box_advanced',
      '#content_moderation_form' => '', // @TODO: Add this later
      '#compare_link' => $compare_link,
      '#follow_widget' => $this->buildBlockInstance('epa_workflow_follow_widget'),
      '#node_details_widget' => $this->buildBlockInstance('epa_node_details'),
      '#box_color' => self::colorToModerationStateMap($node_state),
      '#attributes' => new Attribute(),
    ];

    return $build;
  }

  /**
   * Based on the node in context
   * @param $nid
   * @param $revision1
   * @param $revision2
   *
   * @return array|mixed[]
   */
  public function buildCompareLink($nid, $revision1, $revision2) {
    // Based on the node in context determine if we can have a 'compare' link.

    // Always place the older revision on the left side of the comparison
    // and the newer revision on the right side (however revisions can be
    // compared both ways if we manually change the order of the parameters).
    if ($revision1 > $revision2) {
      $aux = $revision1;
      $revision1 = $revision2;
      $revision2 = $aux;
    }
    // Builds the redirect Url.
    $url = Url::fromRoute(
      'diff.revisions_diff',
      [
        'node' => $nid,
        'left_revision' => $revision1,
        'right_revision' => $revision2,
        'filter' => self::DIFF_FILTER,
      ],
    );

    $link = new Link($this->t('Compare latest draft & live'), $url);
    return $link->toRenderable();
  }

  private function buildBlockInstance(string $block_id, array $block_config = []) {
    $node = $this->getContextValue('node');
    /** @var \Drupal\Core\Block\BlockBase $follow_widget */
    $block = $this->blockManager->createInstance($block_id, $block_config);
    $block->setContextValue('node', $node);
    $access_result = $block->access(\Drupal::currentUser());
    // Return empty render array if user doesn't have access.
    // $access_result can be boolean or an AccessResult class
    if (is_object($access_result) && $access_result->isForbidden() || is_bool($access_result) && !$access_result) {
      // You might need to add some cache tags/contexts.
      return [];
    }
    return $block->build();
  }

}
