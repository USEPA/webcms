<?php

namespace Drupal\epa_migrations\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Database\Connection;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Fetch media entities from document node entity references.
 *
 * Usage:
 *
 * @code
 * process:
 *   field_related_documents
 *     -
 *       plugin: single_value
 *       source: field_related_documents
 *     -
 *       plugin: epa_documents
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "epa_documents"
 * )
 */
class EpaDocuments extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The drupal_7 database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $d7Connection;

  /**
   * Constructs an EpaDocuments plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Database\Connection $d7_database
   *   The drupal_7 database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManager $entity_type_manager, Connection $d7_database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->d7Connection = $d7_database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('epa_migrations.d7_database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $return_ids = [];

    foreach ($value as $document_reference) {

      $nid = $document_reference['target_id'];

      $referenced_documents = $this->d7Connection->select('field_data_field_file', 'fdff')
        ->fields('fdff', ['field_file_fid'])
        ->condition('fdff.entity_id', $nid)
        ->orderBy('fdff.delta')
        ->execute()
        ->fetchAll();

      foreach ($referenced_documents as $document) {
        $media_entity = $this->entityTypeManager->getStorage('media')
          ->load($document->field_file_fid);

        $media_entity_id = $media_entity ? $media_entity->id() : 0;

        $return_ids[] = $media_entity_id;
      }
    }

    return $return_ids;
  }

}
