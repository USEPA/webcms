<?php

namespace Drupal\epa_workflow\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\danse\Entity\Event;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base plugin to retrieve content from danse events.
 *
 * @ingroup views_plugins
 */
abstract class EpaWorkflowReferenceBase extends FieldPluginBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a \Drupal\field\Plugin\views\field\Field object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
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
  public function getEntity(ResultRow $values) {
    $event = $this->getEvent($values);
    if ($event instanceof Event && $payload = $event->getPayload()) {
      return $payload->getEntity();
    }
    return $event;
  }

  /**
   * Gets the Danse entity matching the current row and relationship.
   *
   * @param \Drupal\views\ResultRow $values
   *   An object containing all retrieved values.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Returns the Danse entity matching the values or NULL if there is
   *   no matching entity.
   */
  public function getEvent(ResultRow $values) {
    $event = parent::getEntity($values);
    if ($event instanceof Event) {
      return $event;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

}
