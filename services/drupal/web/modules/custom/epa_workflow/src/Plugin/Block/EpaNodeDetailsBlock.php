<?php

namespace Drupal\epa_workflow\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides an EPA Node Details block.
 *
 * @Block(
 *   id = "epa_node_details",
 *   admin_label = @Translation("EPA Node Details"),
 *   category = @Translation("Custom"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", required = TRUE, label = @Translation("Node"))
 *   }
 * )
 */
class EpaNodeDetailsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new EpaNodeDetailsBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Get the node from the context.
    $node = $this->getContextValue('node');

    if ($node instanceof NodeInterface) {

      // This loads the relationship entity that ties a node to a group.
      $group_contents = \Drupal\group\Entity\GroupContent::loadByEntity($node);

      // $group_contents is an array. We only care about the first one as there should only ever be one node to one group.
      $group_content = reset($group_contents);

      if ($group_content) {
          // Now we can get the group object itself.
          $group = $group_content->getGroup();

          // Once we have the group, we can get field data from it. Because this field is an entity reference field, we can call a special method to load the referenced entity. In this case, the user object.
          $editor_in_chief_entities = $group->get('field_editor_in_chief')->referencedEntities();

          // The referencedEntities() method returns an array as well. We only care about the first one.
          $editor_in_chief = reset($editor_in_chief_entities);

          if ($editor_in_chief) {
              // Now we can build the URL to the user and create a link.
              $editorInChiefLink = \Drupal\Core\Link::fromTextAndUrl($editor_in_chief->getDisplayName(), $editor_in_chief->toUrl())->toString();
          }
      }
      // Review deadline - assuming it's a field on the node.
      if ($node->hasField('field_review_deadline') && !$node->get('field_review_deadline')->isEmpty()) {
        $reviewDeadline = $node->get('field_review_deadline')->date->format('F d, Y');
      } else {
        $reviewDeadline = 'No deadline set';
      }

    // Get the node ID and the latest revision ID.
    $nodeId = $node->id();
    $revisionId = $node->getRevisionId();
    // Create a URL to the latest revision view page.
    $revisionUrl = Url::fromRoute('entity.node.revision', ['node' => $nodeId, 'node_revision' => $revisionId]);
    // Create a link with the revision ID as the link text.
    $revisionLink = Link::fromTextAndUrl($this->t('@revision_id', ['@revision_id' => $revisionId]), $revisionUrl)->toString();

      $build['content'] = [
        '#markup' => $this->t('Node ID: @nid<br>Revision ID: @revision_id<br>Revision saved by: @editor_in_chief<br>Review Deadline: @review_deadline', [
          '@nid' => $node->id(),
          '@revision_id' => $revisionLink,
          '@editor_in_chief' => $editorInChiefLink,
          '@review_deadline' => $reviewDeadline,
        ]),
        '#allowed_tags' => ['br', 'a'], // Allow certain HTML tags.
      ];
    } else {
      // Fallback content if no node is provided.
      $build['content'] = [
        '#markup' => $this->t('No node context provided.'),
      ];
    }

    return $build;
  }
}