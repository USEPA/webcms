<?php

namespace Drupal\epa_web_areas\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\Processor\EntityProcessorProperty;
use Drupal\node\NodeInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api\Utility\Utility;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds the user's soul mate node for indexing.
 *
 * @SearchApiProcessor(
 *   id = "epa_web_areas_group",
 *   label = @Translation("Web Area Group"),
 *   description = @Translation("Enable access to the Web Area group data."),
 *   stages = {
 *     "add_properties" = 20,
 *   }
 * )
 */
class GroupReference extends ProcessorPluginBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|null
   */
  protected $entityTypeManager;

  /**
   * The fields helper.
   *
   * @var \Drupal\search_api\Utility\FieldsHelperInterface|null
   */
  protected $fieldsHelper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $processor->setEntityTypeManager($container->get('entity_type.manager'));
    $processor->setFieldsHelper($container->get('search_api.fields_helper'));

    return $processor;
  }

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  public function getEntityTypeManager() {
    return $this->entityTypeManager ?: \Drupal::entityTypeManager();
  }

  /**
   * Sets the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The new entity type manager.
   *
   * @return $this
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

  /**
   * Retrieves the fields helper.
   *
   * @return \Drupal\search_api\Utility\FieldsHelperInterface
   *   The fields helper.
   */
  public function getFieldsHelper() {
    return $this->fieldsHelper ?: \Drupal::service('search_api.fields_helper');
  }

  /**
   * Sets the fields helper.
   *
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fields_helper
   *   The new fields helper.
   *
   * @return $this
   */
  public function setFieldsHelper(FieldsHelperInterface $fields_helper) {
    $this->fieldsHelper = $fields_helper;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      // Make this processor available to node indexes.
      if ($datasource->getEntityTypeId() === 'node') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource && $datasource->getEntityTypeId() === 'node') {
      $definition = [
        'label' => $this->t('Web Area Group'),
        'description' => $this->t("Enable access to the Web Area group data"),
        'type' => 'entity:group',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['web_area_group'] = new EntityProcessorProperty($definition);
      $properties['web_area_group']->setEntityTypeId('group');
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $node = $item->getOriginalObject()->getValue();

    if (!($node instanceof NodeInterface)) {
      return;
    }

    /** @var \Drupal\search_api\Item\FieldInterface[][] $to_extract */
    $to_extract = [];
    foreach ($item->getFields() as $field) {
      $datasource = $field->getDatasource();
      $property_path = $field->getPropertyPath();
      list($direct, $nested) = Utility::splitPropertyPath($property_path, FALSE);
      if ($datasource
          && $datasource->getEntityTypeId() === 'node'
          && $direct === 'web_area_group') {
        $to_extract[$nested][] = $field;
      }
    }

    if (!$to_extract) {
      return;
    }

    $node = $this->getEntityTypeManager()
      ->getStorage('node')
      ->load($node->id());
    if (!$node) {
      return;
    }

    // This is a pretty hack-y work-around to make property extraction work for
    // Views fields, too. In general, adding entities as field values is a
    // pretty bad idea, so this might blow up in some use cases. If not
    // required, the foreach block should thus be commented out.
    if (isset($to_extract[''])) {
      foreach ($to_extract[''] as $field) {
        $field->setValues([$node]);
      }
      unset($to_extract['']);
    }

    $this->getFieldsHelper()
      ->extractFields($node->getTypedData(), $to_extract, $item->getLanguage());
  }

}
