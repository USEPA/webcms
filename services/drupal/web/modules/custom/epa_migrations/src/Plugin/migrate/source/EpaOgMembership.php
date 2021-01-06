<?php

namespace Drupal\epa_migrations\Plugin\migrate\source;

use Drupal\epa_migrations\EpaIgnoreRowTrait;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Load entities and get their group membership data.
 *
 * Available configuration keys:
 * - d7_entity_type: the entity type to query for in the epa_og_og_membership
 * table
 * - d8_entity_type: the entity type in D8. This mainly applies to media where
 * the d7 entity type is file and the d8 type is media.
 *
 * @MigrateSource(
 *   id = "epa_og_membership",
 *   source_module= "epa_og"
 * )
 */
class EpaOgMembership extends DrupalSqlBase {
  use EpaIgnoreRowTrait;

  /**
   * {@inheritDoc}
   */
  protected $batchSize = 1000;

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (isset($this->configuration['use_og_table'])) {
      $query = $this->select('og_membership', 'eoom');
    }
    else {
      $query = $this->select('epa_og_og_membership', 'eoom');
    }

    $query->condition('eoom.entity_type', $this->configuration['d7_entity_type'])
      ->fields('eoom', ['etid', 'gid']);

    if (isset($this->configuration['node_bundle'])) {
      $query->innerJoin('node', 'n', 'eoom.etid = n.nid');
      $query->condition('n.type', $this->configuration['node_bundle']);
      $query->condition('eoom.entity_type', 'node');
    }

    return $query;
  }

  /**
   * {@inheritDoc}
   */
  public function fields() {
    $fields = [
      'etid' => $this->t('Entity ID'),
      'gid' => $this->t('Group ID'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['etid']['type'] = 'integer';
    $ids['etid']['alias'] = 'e';
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $type = $this->configuration['d8_entity_type'];
    $etid = $row->getSourceProperty('etid');

    // Load the D8 entity.
    $entity = $this->entityTypeManager->getStorage($type)->load($etid);
    if (!$entity) {
      // Entity can't be found, so ignore row
      return $this->ignoreRow($row, "Unable to migrate group relationship due to missing '$type' entity with ID '$etid'.");
    }

    // Make the label for this entity available as a source property.
    $row->setSourceProperty('label', $entity->label());

    // Make the bundle for this entity available as a source property.
    $row->setSourceProperty('bundle', $entity->bundle());

    return parent::prepareRow($row);
  }

}
