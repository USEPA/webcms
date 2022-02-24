<?php
namespace Drupal\epa_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list of all webforms with 'default' in their subject line.
 */
class ProblemWebforms extends ControllerBase {


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
    $ids = $db->query("SELECT SUBSTRING(name, 17) FROM config WHERE name LIKE 'webform.webform.%' AND (data LIKE '%s:7:\"subject\";s:7:\"default\"%' OR data LIKE '%s:4:\"body\";s:7:\"default\"%' OR data LIKE '%s:9:\"from_mail\";s:7:\"default\"%' OR data LIKE '%s:9:\"from_name\";s:7:\"default\"%')", [], ['allow_delimiter_in_query' => TRUE])->fetchCol();

    $storage = $this->container->get('entity_type.manager')->getStorage('webform');
    $forms = $storage->loadMultiple($ids);

    $list = '';
    foreach ($forms as $form) {
      $list .= $form->toLink()->toString() .'<br />';
    }
    return [
      '#markup' => $list,
    ];
  }

}
