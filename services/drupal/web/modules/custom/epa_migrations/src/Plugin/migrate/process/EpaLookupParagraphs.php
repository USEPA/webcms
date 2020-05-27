<?php

namespace Drupal\epa_migrations\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Database\Connection;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extract field collection target Ids..
 *
 * Usage:
 *
 * @code
 * process:
 *   bar:
 *     plugin: epa_lookup_paragraphs
 *     source: field_collection_field
 *     migration: upgrade_d7_paragraph_legal_authorities
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "epa_lookup_paragraphs"
 * )
 */
class EpaLookupParagraphs extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs an EpaLookupParagraphs plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $connection) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $return_ids = [];

    if (isset($this->configuration['migration'])) {
      $migration_table = 'migrate_map_' . $this->configuration['migration'];
    }
    else {
      throw new MigrateException('The "migration" configuration key is required.');
    }

    foreach ($value as $id_array) {
      if (isset($id_array['value'])) {

        $paragraph_ids = $this->connection->select($migration_table, 'mt')
          ->fields('mt', ['destid1', 'destid2'])
          ->condition('mt.sourceid1', $id_array['value'])
          ->execute()
          ->fetchAll();

        if ($paragraph_ids) {
          $return_ids[] = (array) $paragraph_ids[0];
        }
      }
    }

    return $return_ids;

  }

  /**
   * {@inheritdoc}
   */
  public function multiple() {
    return TRUE;
  }

}
