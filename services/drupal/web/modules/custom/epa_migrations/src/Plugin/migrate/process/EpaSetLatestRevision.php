<?php

namespace Drupal\epa_migrations\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\node\Entity\Node;
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
    $d7_vid = $value[1];

    $node_storage = $this->entityTypeManager->getStorage('node');
    $node = $node_storage->load($nid);
    if (!$node) {
      // If the node doesn't exist in D8, we can't do anything here.
      throw new MigrateException('Unable to load node to set latest revision');
    }

    // Get timestamp and state for the current revision.
    $d7_current_revision = $this->d7Connection->select('node_revision_epa_states', 'nres')
      ->fields('nres', ['timestamp', 'state'])
      ->condition('nres.vid', $d7_vid)
      ->execute()
      ->fetchObject();

    // Get all revisions modified since the current revision.
    $d7_forward_revisions = $this->d7Connection->select('node_revision_epa_states', 'nres')
      ->fields('nres', ['vid', 'timestamp', 'state'])
      ->condition('nres.nid', $nid)
      ->condition('nres.timestamp', $d7_current_revision->timestamp, '>')
      ->orderBy('nres.vid', 'DESC')
      ->execute()
      ->fetchAll();

    // Initialize state mapping for state names that differ from d7 to d8.
    $state_map = [
      'draft_review' => 'draft_needs_review',
      'published_review' => 'published_needs_review',
      'published_expire' => 'published_day_til_expire',
      'queued_for_archive' => 'unpublished',
    ];

    // Load current revision in both D7 and D8 for a given node, compare vids.
    //
    // If they do not match, in D8 re-save the vid that we want to make the
    // current revision (as obtained from D7), retaining its same state.
    //
    // If they do match, skip this step and goto forward revision logic.
    if ($node->getRevisionId() !== $d7_vid) {
      // There's a vid mismatch, re-save d7_current_revision in D8 with the
      // correct state.
      $new_current_revision_state = $state_map[$d7_current_revision->state] ?? $d7_current_revision->state;

      $new_current_revision = $this->entityTypeManager
        ->getStorage('node')
        ->loadRevision($d7_vid);

      $new_current_revision->createDuplicate();
      $new_current_revision->set('moderation_state', $new_current_revision_state);
      $new_current_revision->setRevisionLogMessage(t('During D7 migration, this revision was re-set as the current revision.&emsp;|&emsp;') . $new_current_revision->getRevisionLogMessage());
      $new_current_revision->save();

      $this->logger->notice('Updated current revision for Node ID: %nid,  D7 Revision ID: %vid.', ['%nid' => $nid, '%vid' => $d7_vid]);
    }

    // Now that we've handled any potential vid mismatches, process forward
    // revisions to set the correct draft.
    if (count($d7_forward_revisions) > 0) {

      $newer_draft_revision = $this->getHeaviestDraftRevision($d7_forward_revisions);

      // If the newer draft revision has a vid lower than the latest revision,
      // we want to give it a new vid so it is set as the latest revision.
      $d8_latest_revision_id = $node_storage->getLatestRevisionId($node->id());
      if ($newer_draft_revision->vid < $d8_latest_revision_id) {

        $new_latest_revision_state = $state_map[$newer_draft_revision->state] ?? $newer_draft_revision->state;

        $new_latest_revision = $this->entityTypeManager
          ->getStorage('node')
          ->loadRevision($newer_draft_revision->vid);

        if ($new_latest_revision) {
          $new_latest_revision->createDuplicate();
          $new_latest_revision->set('moderation_state', $new_latest_revision_state);
          $new_latest_revision->setRevisionLogMessage(t('During D7 migration, this revision was set as the latest revision.&emsp;|&emsp;') . $new_latest_revision->getRevisionLogMessage());
          $new_latest_revision->save();

          $this->logger->notice('Updated latest revision for Node ID: %nid,  Revision ID: %vid.', ['%nid' => $nid, '%vid' => $d7_vid]);
        }
      }
    }

    // Now that we've set the correct revisions, let's turn on pathauto and
    // reset the cache for this node.
    \Drupal::keyValue('pathauto_state.node')->set($nid, 1);
    $node_storage->resetCache([$nid]);
    return TRUE;
  }

  /**
   * Helper method to get the newest, heaviest draft revision.
   *
   * @param array $forward_revisions
   *   The revisions to iterate through.
   *
   * @return object
   *   The heaviest draft revision.
   */
  private function getHeaviestDraftRevision(array $forward_revisions) {
    $state_weights = [
      'draft_approved' => 300,
      'draft_review' => 200,
      'draft' => 100,
    ];

    // Initialize heaviest revision data with most recent forward revision.
    $heaviest_revision = $forward_revisions[0];
    $heaviest_revision_weight = $state_weights[$heaviest_revision->state];

    foreach ($forward_revisions as $fr) {
      if ($state_weights[$fr->state] > $heaviest_revision_weight && $fr->timestamp > $heaviest_revision->timestamp) {
        $heaviest_revision = $fr;
        $heaviest_revision_weight = $state_weights[$fr->state];
      }
    }

    return $heaviest_revision;
  }

}
