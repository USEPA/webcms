<?php

namespace Drupal\epa_workflow\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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

    $group_contents = $this->entityTypeManager->getStorage('group_content')->loadByEntity($node);

      // $group_contents is an array. We only care about the first one as there should only ever be one node to one group.
      $group_content = reset($group_contents);

      if ($group_content) {
          // Now we can get the group object itself.
          $group = $group_content->getGroup();

          // Once we have the group, we can get field data from it. Because this field is an entity reference field, we can call a special method to load the referenced entity. In this case, the user object.
          $editor_in_chief_entities = $group->get('field_editor_in_chief')->referencedEntities();

          // The referencedEntities() method returns an array as well. We only care about the first one.
          $editor_in_chief = reset($editor_in_chief_entities);

         $editor_in_chief_link = Link::fromTextAndUrl($editor_in_chief->getDisplayName(), $editor_in_chief->toUrl())->toString();
      }
      // Review deadline - assuming it's a field on the node.
      if ($node->hasField('field_review_deadline') && !$node->get('field_review_deadline')->isEmpty()) {
        $review_deadline = $node->get('field_review_deadline')[0];
        $value  = $review_deadline->get('value');
        $timestamp = $value->getDateTime()->getTimestamp();
        $review_deadline = \Drupal::service('date.formatter')->format($timestamp, 'formal_datetime');
      } else {
        $review_deadline = 'No deadline set';
      }

    // Get the node ID and the latest revision ID.
    $node_id = $node->id();
    $revision_id = $node->getRevisionId();
    // Create a URL to the latest revision view page.
    $revision_url = Url::fromRoute('entity.node.revision', ['node' => $node_id, 'node_revision' => $revision_id], ['absolute' => TRUE]);
    // Create a link with the revision ID as the link text.
    $revision_link = Link::fromTextAndUrl($this->t('@revision_id', ['@revision_id' => $revision_id]), $revision_url)->toString();

      $build['content'] = [
        '#theme' => 'epa_node_details',
        '#nid' => $node->id(),
        '#revision_url' => $revision_url,
        '#revision_link' => $revision_link,
        '#editor_in_chief' => $editor_in_chief_link,
        '#review_deadline' => $review_deadline,
      ];

    return $build;
  }
}
