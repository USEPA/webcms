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
 * Generate log message of state history for a given revision id.
 *
 * Usage:
 *
 * @code
 * process:
 *   bar:
 *     plugin: epa_generate_revision_log
 *     source: vid
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "epa_generate_revision_log"
 * )
 */
class EpaGenerateRevisionLog extends ProcessPluginBase implements ContainerFactoryPluginInterface {

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
   * Constructs an EpaLookupParagraphs plugin.
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
      $container->get('epa_migrations.d7_database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $epa_states_history = $this->d7Connection->select('node_revision_epa_states_history', 'h')
      ->fields('h', ['state', 'timestamp', 'uid', 'log'])
      ->condition('h.vid', $value)
      ->orderby('h.timestamp', 'DESC')
      ->execute()
      ->fetchAll();

    if ($epa_states_history) {
      $messages = [];

      foreach ($epa_states_history as $history) {
        $date = date('m/d/Y h:iA', $history->timestamp);
        $user = $this->entityTypeManager->getStorage('user')->load($history->uid);
        $user = $user ? $user->label() : 'unknown user';
        $message = "$date $user => {$history->state}";
        if ($history->log) {
          $message .= ": {$history->log}";
        }

        $messages[] = $message;
      }

      $log_message = implode("&emsp;|&emsp;", $messages);
    }
    else {
      $log_message = '';
    }

    return $log_message;

  }

}
