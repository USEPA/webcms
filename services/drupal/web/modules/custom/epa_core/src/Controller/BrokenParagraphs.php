<?php
namespace Drupal\epa_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list of all pages broken due paragraph cloning.
 */
class BrokenParagraphs extends ControllerBase {


  /**
   * The dependency injection container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * Construct a new object.
   */
  public function __construct(ContainerInterface $container) {
    $this->container = $container;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container);
  }

  /**
   * Returns a simple list.
   *
   * @return array
   *   A renderable array.
   */
  public function list() {
    $db = $this->container->get('database');
    $ids = $db->query("SELECT DISTINCT nf.entity_id
FROM {node__field_paragraphs} nf
LEFT JOIN {paragraphs_item_field_data} pid
    ON nf.entity_id != pid.parent_id
        AND pid.parent_type = 'node'
        AND pid.parent_field_name = 'field_paragraphs'
        AND field_paragraphs_target_id = pid.id
LEFT JOIN {node_field_data} nd
    ON nf.entity_id = nd.nid
LEFT JOIN {node_field_data} nd2
    ON nd2.nid = pid.parent_id
WHERE nd.status = 1
        AND pid.parent_id IS NOT NULL")->fetchCol();
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $nodes = $storage->loadMultiple($ids);

    $ids = $db->query("SELECT DISTINCT nf.entity_id
FROM {node__field_paragraphs_1} nf
LEFT JOIN {paragraphs_item_field_data} pid
    ON nf.entity_id != pid.parent_id
        AND pid.parent_type = 'node'
        AND pid.parent_field_name = 'field_paragraphs_1'
        AND field_paragraphs_1_target_id = pid.id
LEFT JOIN {node_field_data} nd
    ON nf.entity_id = nd.nid
LEFT JOIN {node_field_data} nd2
    ON nd2.nid = pid.parent_id
WHERE nd.status = 1
        AND pid.parent_id IS NOT NULL")->fetchCol();
    $nodes = array_replace($nodes, $storage->loadMultiple($ids));

    $list = '';
    foreach ($nodes as $node) {
      $list .= $node->toLink()->toString() .'<br />';
    }
    return [
      '#markup' => $list,
    ];
  }

}
