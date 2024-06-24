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
   *   The block plugin manager service.
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
      $container->get('plugin.manager.block'),
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

    if (\Drupal::currentUser()->isAuthenticated()) {
      return AccessResult::allowed();
    }

    return AccessResultForbidden::forbidden();

  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getContextValue('node');

    try {
      $moderation_state_id = $node->get('moderation_state')->getString();
      $box_color = $this->moderationStateToColorMap($moderation_state_id);
    }
    catch (\Exception $e) {
      // @todo log some error
      $box_color = 'yellow';
    }

    // @todo: Determine cache strategy for this block. It's going to be dependent on node revision we're looking at and user.
    $build['#cache']['max-age'] = 0;

    $build['content'] = [
      '#theme' => 'epa_content_info_box_advanced',
      '#box_color' => $box_color,
      '#compare_link' => $this->buildCompareLink(),
      '#content_moderation_form' => $this->buildBlockInstance('epa_workflow_content_moderation_form'), // @todo: Add this later
      '#follow_widget' => $this->buildBlockInstance('epa_workflow_follow_widget'),
      '#node_details_widget' => $this->buildBlockInstance('epa_node_details'),
      '#attributes' => new Attribute(),
    ];

    return $build;
  }

  /**
   * Based on the node in context, generate a 'Compare latest draft & live' link.
   *
   * @return array|mixed[]
   *   The render array for the compare link or an empty array if not available.
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function buildCompareLink() {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getContextValue('node');

    if ($node->isPublished() && $node->isLatestRevision()) {
      // This means the latest revision is the published revision so nothing to
      // compare.
      return [];
    }

    $published_revision_id = $this->getPublishedRevisionId();
    // If no published revision ID then exit out.
    if (!$published_revision_id) {
      return [];
    }

    $latest_revision_id = $this->getLatestRevisionId();
    // If no latest revision ID then exit out.
    if (!$latest_revision_id) {
      return [];
    }

    // Ensure that those revision IDs are not the same to ensure we have
    // revisions to compare against
    if ($published_revision_id != $latest_revision_id) {
      return $this->buildCompareLinkRenderArray($node->id(), $published_revision_id, $latest_revision_id);
    }

    return [];
  }

  /**
   * Helper method to get the published revision ID of the node in context.
   *
   * @return false|int
   *   The published revision ID of the node in context or FALSE if none exist.
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getPublishedRevisionId() {
    /** @var \Drupal\node\NodeInterface  $node */
    $node = $this->getContextValue('node');

    if ($node->isPublished()) {
      return $node->getRevisionId();
    }
    else {
      // Determine if we have a published revision at all
      $published_revision = \Drupal::entityTypeManager()
        ->getStorage($node->getEntityTypeId())
        ->getQuery()
        ->accessCheck()
        ->condition('nid', $node->id())
        ->condition('status', 1)
        ->execute();

      if (!empty($published_revision)) {
        // If we have a record here that means there's a published revision of the
        // node.
        return array_key_first($published_revision);
      }
    }

    return FALSE;
  }

  /**
   * Helper method to get the latest revision ID of the node in context.
   *
   * @return false|int
   *   The latest revision ID for the node in context or FALSE if none exist.
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getLatestRevisionId() {
    /** @var \Drupal\node\NodeInterface  $node */
    $node = $this->getContextValue('node');

    // Are we looking at the latest revision? If so, that's what we compare to.
    if ($node->isLatestRevision()) {
      return $node->getRevisionId();
    }
    else {
      $latest_revision = \Drupal::entityTypeManager()
        ->getStorage($node->getEntityTypeId())
        ->getQuery()
        ->condition('nid', $node->id())
        ->latestRevision()
        ->execute();

      if (!empty($latest_revision)) {
        return array_key_first($latest_revision);
      }
    }

    // Should never get here.
    return FALSE;
  }

  /**
   * Generates a diff module compare link render array.
   *
   * @param $nid
   *   The node ID.
   * @param $published_revision
   *   The published revision ID.
   * @param $latest_revision
   *   The latest revision ID.
   *
   * @return array|mixed[]
   *   The render array of the compare link.
   */
  private function buildCompareLinkRenderArray($nid, $published_revision, $latest_revision) {
    $url = Url::fromRoute(
      'diff.revisions_diff',
      [
        'node' => $nid,
        'left_revision' => $published_revision,
        'right_revision' => $latest_revision,
        'filter' => self::DIFF_FILTER,
      ],
    );

    $link = new Link($this->t('Compare latest draft & live'), $url);
    return $link->toRenderable();
  }

  /**
   * Helper method to load and build the render array for a specified block.
   *
   * @param string $block_id
   *   The block plugin's ID to build.
   * @param array $block_config
   *   (Optional) Any block configuration to pass along.
   *
   * @return array
   *   The render array of the loaded block.
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
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
