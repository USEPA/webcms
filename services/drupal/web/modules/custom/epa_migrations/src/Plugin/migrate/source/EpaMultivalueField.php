<?php

namespace Drupal\epa_migrations\Plugin\migrate\source;

use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;
use Drupal\migrate\MigrateException;

/**
 * Load values from a multivalue field and append the delta to the source id.
 *
 * Available configuration keys:
 * - field: the name of the field to load.
 * - bundle: (optional) the bundle to limit the query on.
 *
 * @MigrateSource(
 *   id = "epa_multivalue_field",
 *   source_module = "field"
 * )
 */
class EpaMultivalueField extends DrupalSqlBase {

  /**
   * {@inheritDoc}
   */
  protected $batchSize = 1000;

  const HIGH_WATER_EXPRESSION = "CONCAT(fd.revision_id, '-', fd.delta)";

  /**
   * Intentionally does not include "fd."
   */
  const HIGH_WATER_ALIAS = "unique_id";

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (isset($this->configuration['field'])) {
      $field_table = 'field_revision_' . $this->configuration['field'];

      $query = $this->select($field_table, 'fd')
        ->fields('fd');

      // Add an expression to let us generate a unique field for use as high water mark.
      $query->addExpression(self::HIGH_WATER_EXPRESSION, 'unique_id');

      if ($this->configuration['bundle']) {
        $query->condition('fd.bundle', $this->configuration['bundle']);
      }

      return $query;
    }
    else {
      throw new MigrateException('The "field_name" configuration key is required.');
    }
  }

  /**
   * {@inheritDoc}
   */
  public function fields() {
    $fields = [
      'entity_id' => $this->t('Entity ID'),
      'revision_id' => $this->t('Revision ID'),
      'delta' => $this->t('Delta'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['revision_id']['type'] = 'integer';
    $ids['revision_id']['alias'] = 'fd';
    $ids['delta']['type'] = 'integer';
    $ids['delta']['alias'] = 'fd';
    return $ids;
  }

  /**
   * This has been overwritten from SqlBase in order to use the expression of fields
   * in the high water condition because MySQL does not allow a condition on an
   * aliased expression.
   *
   * @see SqlBase::initializeIterator
   */
  protected function initializeIterator() {

    // Initialize the batch size.
    if ($this->batchSize == 0 && isset($this->configuration['batch_size'])) {
      // Valid batch sizes are integers >= 0.
      if (is_int($this->configuration['batch_size']) && ($this->configuration['batch_size']) >= 0) {
        $this->batchSize = $this->configuration['batch_size'];
      }
      else {
        throw new MigrateException("batch_size must be greater than or equal to zero");
      }
    }

    // If a batch has run the query is already setup.
    if ($this->batch == 0) {
      $this->prepareQuery();

      // Get the key values, for potential use in joining to the map table.
      $keys = [];

      // The rules for determining what conditions to add to the query are as
      // follows (applying first applicable rule):
      // 1. If the map is joinable, join it. We will want to accept all rows
      //    which are either not in the map, or marked in the map as NEEDS_UPDATE.
      //    Note that if high water fields are in play, we want to accept all rows
      //    above the high water mark in addition to those selected by the map
      //    conditions, so we need to OR them together (but AND with any existing
      //    conditions in the query). So, ultimately the SQL condition will look
      //    like (original conditions) AND (map IS NULL OR map needs update
      //      OR above high water).
      $conditions = $this->query->orConditionGroup();
      $condition_added = FALSE;
      $added_fields = [];
      if ($this->mapJoinable()) {
        // Build the join to the map table. Because the source key could have
        // multiple fields, we need to build things up.
        $count = 1;
        $map_join = '';
        $delimiter = '';
        foreach ($this->getIds() as $field_name => $field_schema) {
          if (isset($field_schema['alias'])) {
            $field_name = $field_schema['alias'] . '.' . $this->query->escapeField($field_name);
          }
          $map_join .= "$delimiter$field_name = map.sourceid" . $count++;
          $delimiter = ' AND ';
        }

        $alias = $this->query->leftJoin($this->migration->getIdMap()
          ->getQualifiedMapTableName(), 'map', $map_join);
        $conditions->isNull($alias . '.sourceid1');
        $conditions->condition($alias . '.source_row_status', MigrateIdMapInterface::STATUS_NEEDS_UPDATE);
        $condition_added = TRUE;

        // And as long as we have the map table, add its data to the row.
        $n = count($this->getIds());
        for ($count = 1; $count <= $n; $count++) {
          $map_key = 'sourceid' . $count;
          $this->query->addField($alias, $map_key, "migrate_map_$map_key");
          $added_fields[] = "$alias.$map_key";
        }
        if ($n = count($this->migration->getDestinationIds())) {
          for ($count = 1; $count <= $n; $count++) {
            $map_key = 'destid' . $count++;
            $this->query->addField($alias, $map_key, "migrate_map_$map_key");
            $added_fields[] = "$alias.$map_key";
          }
        }
        $this->query->addField($alias, 'source_row_status', 'migrate_map_source_row_status');
        $added_fields[] = "$alias.source_row_status";
      }
      // 2. If we are using high water marks, also include rows above the mark.
      //    But, include all rows if the high water mark is not set.
      if ($this->getHighWaterProperty()) {
        $high_water_field = $this->getHighWaterField();
        $high_water = $this->getHighWater();
        // We check against NULL because 0 is an acceptable value for the high
        // water mark.
        if ($high_water !== NULL) {
          // Note this used to use the conditions API
          $this->query->where(self::HIGH_WATER_EXPRESSION . " > :hw", [
            'hw' => $high_water,
          ]);
        }
        // Always sort by the high water field, to ensure that the first run
        // (before we have a high water value) also has the results in a
        // consistent order.
        $this->query->orderBy(self::HIGH_WATER_ALIAS);
      }
      // If the query has a group by, our added fields need it too, to keep the
      // query valid.
      // @see https://dev.mysql.com/doc/refman/5.7/en/group-by-handling.html
      $group_by = $this->query->getGroupBy();
      if ($group_by && $added_fields) {
        foreach ($added_fields as $added_field) {
          $this->query->groupBy($added_field);
        }
      }
    }

    // Download data in batches for performance.
    if (($this->batchSize > 0)) {
      $this->query->range($this->batch * $this->batchSize, $this->batchSize);
    }
    $statement = $this->query->execute();
    $statement->setFetchMode(\PDO::FETCH_ASSOC);
    return new \IteratorIterator($statement);
  }

}
