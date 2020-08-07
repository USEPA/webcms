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
 * Lookup paragraphs migrated with a multivalue field.
 *
 * Usage:
 *
 * @code
 * process:
 *   bar:
 *     plugin: epa_lookup_multivalue_paragraphs
 *     source: nid
 *     migration: upgrade_d7_paragraph_legal_authorities
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "epa_lookup_multivalue_paragraphs"
 * )
 */
class EpaLookupMultivalueParagraphs extends ProcessPluginBase implements ContainerFactoryPluginInterface {

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
   * Constructs an EpaLookupMultivalueParagraphs plugin.
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
    if (isset($this->configuration['migration'])) {
      $migration_table = 'migrate_map_' . $this->configuration['migration'];
    }
    else {
      throw new MigrateException('The "migration" configuration key is required.');
    }

    $paragraph_ids = $this->connection->select($migration_table, 'mt')
      ->fields('mt', ['destid1', 'destid2'])
      ->condition('mt.sourceid1', $value)
      ->orderBy('mt.sourceid2')
      ->execute()
      ->fetchAll();

    $return_ids = [];

    if ($paragraph_ids) {
      foreach ($paragraph_ids as $ids) {
        $return_ids[] = (array) $ids;
      }
      return $return_ids;
    }

  }

  /**
   * {@inheritdoc}
   */
  public function multiple() {
    return TRUE;
  }

}
