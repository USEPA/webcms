<?php

namespace Drupal\epa_web_areas\Plugin\Menu;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides route parameters needed to link to the group dashboard.
 */
class NodeGroupTab extends LocalTaskDefault implements ContainerFactoryPluginInterface {


  /**
   * GroupContent storage.
   *
   * @var \Drupal\group\Entity\Storage\GroupContentStorageInterface
   */
  protected $groupStorage;

  /**
   * Construct the NodeGroupTab object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->groupStorage = $entity_type_manager->getStorage('group_content');
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
  public function getRouteParameters(RouteMatchInterface $route_match) {
    $node = $route_match->getParameter('node');

    if ($node instanceof NodeInterface) {
      /*
       * Nodes don't have a relationship to a Group, but when content is made in
       * a Group it creates a GroupContent entity which serves the purpose of
       * creating a reference between an entity and a group.
       */
      $gc = $this->groupStorage->loadByEntity($node);

      if (is_array($gc) && !empty($gc)) {
        $gc = reset($gc);

        return ['group' => $gc->getGroup()->id()];
      }
    }

    // Should never get here.
    return [];
  }

}
