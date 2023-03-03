<?php

namespace Drupal\epa_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list of all webforms with referenced by multiple nodes.
 */
class WebformsMultipleNodes extends ControllerBase {


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
    $ids = $db->query("select webform_target_id from {node__webform} GROUP BY webform_target_id having count(entity_id) > 1; ")->fetchCol();

    $storage = $this->container->get('entity_type.manager')->getStorage('webform');
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $forms = $storage->loadMultiple($ids);

    $list = '';
    foreach ($forms as $form) {
      $list .= '<strong>Form:</strong> ' . $form->toLink()->toString() . ' - referenced by:<br />';
      $node_ids = $db->query("select entity_id from {node__webform} where webform_target_id = '" . $form->id() . "';")->fetchCol();
      $nodes = $node_storage->loadMultiple($node_ids);
      foreach ($nodes as $node) {
        $list .= '&emsp;&emsp;<strong>Node:</strong> ' . $node->toLink()->toString() . '<br />';
      }
      $list .= '<br />';
    }
    return [
      '#markup' => $list,
    ];
  }

}
