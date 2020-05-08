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
 * Consolidate d7 deprecated taxonomy terms to d8 formatted keywords.
 *
 * Usage:
 *
 * @code
 * process:
 *   bar:
 *     plugin: epa_keywords
 *     source: nid
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "epa_keywords"
 * )
 */
class EpaKeywords extends ProcessPluginBase implements ContainerFactoryPluginInterface {

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
   * Constructs an EpaMediaWysiwygFilter plugin.
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

    $nid = $value;

    // Get all the term ids assigned to this node in D7.
    $tids = $this->d7Connection->select('taxonomy_index', 'ti')
      ->fields('ti', ['tid'])
      ->condition('ti.nid', $nid)
      ->execute()
      ->fetchCol();

    if ($tids) {
      $vids_to_migrate = [1, 3, 7, 9, 13, 15, 19, 21, 25, 27, 29, 31];
      // Get vids and names for all the terms assigned to this node.
      $all_term_data = $this->d7Connection->select('taxonomy_term_data', 'ttd')
        ->fields('ttd', ['tid', 'vid', 'name'])
        ->condition('ttd.tid', $tids, 'IN')
        ->execute()
        ->fetchAll();

      foreach ($tids as $tid) {
        // Check if this term has already been migrated.
        $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
        $existing_term = $term_storage->loadByProperties(['field_legacy_d7_tid' => $tid]);

        if ($existing_term) {
          // Add this D8 term id to our return array and we're done.
          $existing_term = array_pop($existing_term);
          $return_ids[] = $existing_term->id();
        }
        else {
          // Find the object for this term.
          $term_data = array_filter($all_term_data, function ($term_object) use ($tid) {
            return $term_object->tid === $tid;
          });

          $term_data = array_pop($term_data);

          // Ensure the this term is a member of the vids to consolidate.
          if (in_array($term_data->vid, $vids_to_migrate)) {

            // Build the hierarchy for this term.
            $ancestors = $this->getTidAncestors($tid);

            // Get the names of the ancestors so we can build a term.
            if (count($ancestors) > 0) {
              $ancestor_queried_names = $this->d7Connection->select('taxonomy_term_data', 'ttd')
                ->fields('ttd', ['tid', 'name'])
                ->condition('ttd.tid', $ancestors, "IN")
                ->execute()
                ->fetchAllAssoc('tid');

              $ordered_names = array_map(function ($ancestor_tid) use ($ancestor_queried_names) {
                return $ancestor_queried_names[$ancestor_tid]->name;
              }, $ancestors);

              $compound_name = implode(" > ", $ordered_names);
              $compound_name .= " > {$term_data->name}";
            }
            else {
              $compound_name = $term_data->name;
            }

            // Create a new term in the keywords vocabulary and add the id to
            // the list of ids we'll return.
            $new_term = $term_storage->create([
              'vid' => 'keywords',
              'field_legacy_d7_tid' => $tid,
              'name' => $compound_name,
            ]);

            $new_term->save();

            $return_ids[] = $new_term->id();
          }
        }
      }
    }

    return $return_ids;

  }

  /**
   * Given a term id, query the hierarchy for all of its ancestors.
   *
   * @param int $term_tid
   *   The term id.
   * @param array $ancestors
   *   The ancestors collected so far.
   *
   * @return array
   *   The ancestor term ids.
   */
  private function getTidAncestors(int $term_tid, array $ancestors = []) {

    if ($term_tid !== '0') {
      $parent_tid = $this->d7Connection->select('taxonomy_term_hierarchy', 'tth')
        ->fields('tth', ['parent'])
        ->condition('tth.tid', $term_tid)
        ->execute()
        ->fetchField();

      if ($parent_tid !== '0') {
        $ancestors[] = $parent_tid;
        return $this->getTidAncestors($parent_tid, $ancestors);
      }
      else {
        return array_reverse($ancestors);
      }
    }
  }

}
