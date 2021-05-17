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
    $vids = [1, 3, 7, 9, 13, 15, 19, 21, 25, 27, 29, 31];
    $env_lang = getenv('WEBCMS_LANG');

    // Vocabs are a bit different on the spanish site.
    if ($env_lang == 'es') {
      $vids = [1, 3, 7, 9, 13, 15, 21, 23, 27, 29, 31, 33];
    }

    // Get all the term ids assigned to this node in D7.
    $query = $this->d7Connection->select('taxonomy_index', 'ti')
      ->fields('ti', ['tid']);
    $query->join('taxonomy_term_data', 'td', 'td.tid = ti.tid');
    $query->fields('td', ['vid','name'])
      ->condition('ti.nid', $nid)
      ->condition('td.vid', $vids,'IN');

    $terms = $query->execute()
      ->fetchAll();

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    foreach ($terms as $term_data) {
      // Check if this term has already been migrated.
      $existing_term = $term_storage->load($term_data->tid);

      if ($existing_term) {
        // Add this term id to our return array and we're done.
        $return_ids[] = $term_data->tid;
      }
      else {
        // Build the hierarchy for this term.
        $ancestors = $this->getTidAncestors($term_data->tid);

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
          'tid' => $term_data->tid,
          'vid' => 'keywords',
          'name' => $compound_name,
        ]);

        $new_term->save();

        $return_ids[] = $new_term->id();
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
