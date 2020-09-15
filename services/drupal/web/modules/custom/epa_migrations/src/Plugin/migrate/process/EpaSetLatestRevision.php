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

    // Get all revisions modified since the current revision.
    $forward_revisions = $this->d7Connection->select('node_revision_epa_states', 'nres')
      ->fields('nres', ['vid', 'timestamp', 'state'])
      ->condition('nres.nid', $nid)
      ->condition('nres.timestamp', $current_revision->timestamp, '>')
      ->orderBy('nres.vid', 'DESC')
      ->execute()
      ->fetchAll();

    if (count($forward_revisions) > 0) {
      $forward_revision_states = [];

      foreach ($forward_revisions as $fr) {
        $forward_revision_states[] = $fr->state;
      }

      $forward_revision_states = array_unique($forward_revision_states);

      switch ($current_revision->state) {

        case 'published':
          // If there's an unpublished forward revision, create a new draft
          // based on the current revision if the unpublished forward revision
          // vid is higher than the current revision vid.
          if (in_array('unpublished', $forward_revision_states)) {
            $newer_unpublished_revision = $this->getNewestRevisionByState($forward_revisions, 'unpublished');
            if ($newer_unpublished_revision->vid > $current_revision->vid) {
              $new_latest_revision = $current_revision;
            }
            else {
              $new_latest_revision = FALSE;
            }

          }

          // If there's a draft forward revision, create a new draft based on
          // the draft forward revision if the draft forward revision has a
          // lower vid than the current revision.
          if (in_array('draft', $forward_revision_states) || in_array('draft_review', $forward_revision_states) || in_array('draft_approved', $forward_revision_states)) {
            $newer_draft_revision = $this->getHeaviestDraftRevision($forward_revision_states);
            if ($newer_draft_revision->vid < $current_revision->vid) {
              $new_latest_revision = $newer_draft_revision;
            }
          }
          break;

        case 'unpublished':
          // If there's an unpublished forward revision, create a new draft
          // based on the current revision if the unpublished forward revision
          // vid is lower than the current revision vid.
          if (in_array('unpublished', $forward_revision_states)) {
            $new_unpublished_revision = $this->getNewestRevisionByState($forward_revisions, 'unpublished');
            if ($new_unpublished_revision->vid < $current_revision->vid) {
              $new_latest_revision = $current_revision;
            }
            else {
              $new_latest_revision = FALSE;
            }
          }

          // If there's a draft forward revision, create a new draft based on
          // the draft forward revision if the draft forward revision has a
          // lower vid than the current revision.
          if (in_array('draft', $forward_revision_states) || in_array('draft_review', $forward_revision_states) || in_array('draft_approved', $forward_revision_states)) {            $newer_draft_revision = $this->getHeaviestDraftRevision($forward_revision_states);
            if ($newer_draft_revision->vid < $current_revision->vid) {
              $new_latest_revision = $newer_draft_revision;
            }
          }
          break;

        case 'draft':
        case 'draft_review':
        case 'draft_approved':
          // If there's a draft forward revision, create a new draft based on
          // the draft forward revision if the draft forward revision has a
          // lower vid than the current revision.
          if (in_array('draft', $forward_revision_states) || in_array('draft_review', $forward_revision_states) || in_array('draft_approved', $forward_revision_states)) {            $newer_draft_revision = $this->getHeaviestDraftRevision($forward_revision_states);
            if ($newer_draft_revision->vid < $current_revision->vid) {
              $new_latest_revision = $newer_draft_revision;
            }
          }
          break;

      }

      if (isset($new_latest_revision) && $new_latest_revision->vid !== $latest_revision->vid) {
        $state_map = [
          'draft_review' => 'draft_needs_review',
        ];

        $new_latest_revision_state = $state_map[$new_latest_revision->state] ?? $new_latest_revision->state;

        $new_latest_revision = $this->entityTypeManager
          ->getStorage('node')
          ->loadRevision($new_latest_revision->vid);

        $new_latest_revision->createDuplicate();
        $new_latest_revision->set('moderation_state', $new_latest_revision_state);
        $new_latest_revision->setRevisionLogMessage(t('During D7 migration, this revision was set as the latest revision.&emsp;|&emsp;') . $new_latest_revision->getRevisionLogMessage());
        $new_latest_revision->save();

        $this->logger->notice('Updated latest revision for Node ID: %nid,  Revision ID: %vid.', ['%nid' => $nid, '%vid' => $current_vid]);

        return TRUE;
      }
    }

    return FALSE;
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

  /**
   * Get the latest revision of a specified state.
   *
   * @param array $forward_revisions
   *   The revisions to iterate through.
   * @param string $state
   *   The state to look for.
   *
   * @return object
   *   The latest revision in the specified state.
   */
  private function getNewestRevisionByState(array $forward_revisions, string $state) {
    foreach ($forward_revisions as $fr) {
      if ($fr->state == $state) {
        return $fr;
      }
    }
  }

}
