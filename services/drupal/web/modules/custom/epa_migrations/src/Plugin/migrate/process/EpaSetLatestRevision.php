<?php

namespace Drupal\epa_migrations\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Set latest revision for a node based on custom rules.
 *
 * Usage:
 *
 * @code
 * process:
 *   bar:
 *     plugin: epa_set_latest_revision
 *     source:
 *       - tnid
 *       - vid
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "epa_set_latest_revision"
 * )
 */
class EpaSetLatestRevision extends ProcessPluginBase implements ContainerFactoryPluginInterface {
  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

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
   * Constructs an EpaScheduledTransition plugin.
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
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManager $entity_type_manager, Connection $d7_database, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->d7Connection = $d7_database;
    $this->logger = $logger_factory->get('epa_migrations');
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
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $nid = $value[0];
    $current_vid = $value[1];

    // Get timestamp and state for the current revision.
    $current_revision = $this->d7Connection->select('node_revision_epa_states', 'nres')
      ->fields('nres', ['timestamp', 'state'])
      ->condition('nres.vid', $current_vid)
      ->execute()
      ->fetchObject();

    // Get the timestamp and state for the latest revision.
    $latest_revision = $this->d7Connection->select('node_revision_epa_states', 'nres')
      ->fields('nres', ['vid', 'timestamp', 'state'])
      ->condition('nres.nid', $nid)
      ->orderBy('nres.vid', 'DESC')
      ->execute()
      ->fetchObject();

    // Get all draft revisions modified since the current revision.
    $draft_revisions = $this->d7Connection->select('node_revision_epa_states', 'nres')
      ->fields('nres', ['vid', 'timestamp', 'state'])
      ->condition('nres.nid', $nid)
      ->condition('nres.state', ['draft_approved', 'draft_review', 'draft'], 'IN')
      ->condition('nres.timestamp', $current_revision->timestamp, '>')
      ->orderBy('nres.vid', 'DESC')
      ->execute()
      ->fetchAll();

    if (count($draft_revisions) > 0) {
      // Ensure the heaviest, newest (by timestamp) revision is the latest
      // revision (by vid).
      $state_weights = [
        'draft_approved' => 300,
        'draft_review' => 200,
        'draft' => 100,
      ];

      $state_map = [
        'draft_approved' => 'draft_approved',
        'draft_review' => 'draft_needs_review',
        'draft' => 'draft',
      ];

      // Initialize heaviest revision data with most recent draft revision.
      $heaviest_revision = $draft_revisions[0];
      $heaviest_revision_weight = $state_weights[$heaviest_revision->state];
      $heaviest_revision_state = $state_map[$heaviest_revision->state];

      foreach ($draft_revisions as $dr) {
        if ($state_weights[$dr->state] > $heaviest_revision_weight && $dr->timestamp > $heaviest_revision->timestamp) {
          $heaviest_revision = $dr;
          $heaviest_revision_weight = $state_weights[$dr->state];
          $heaviest_revision_state = $state_map[$dr->state];
        }
      }

      if ($heaviest_revision->vid !== $latest_revision->vid) {
        $heaviest_revision = $this->entityTypeManager
          ->getStorage('node')
          ->loadRevision($heaviest_revision->vid);

        $heaviest_revision->createDuplicate();
        $heaviest_revision->set('moderation_state', $heaviest_revision_state);
        $heaviest_revision->setRevisionLogMessage(t('During D7 migration, this revision was set as the latest revision because it was edited after this node was last published.&emsp;|&emsp;') . $heaviest_revision->getRevisionLogMessage());
        $heaviest_revision->save();

        $this->logger->notice('Updated latest revision for Node ID: %nid,  Revision ID: %vid.', ['%nid' => $nid, '%vid' => $current_vid]);

        return TRUE;
      }

    }

    return FALSE;
  }

}
