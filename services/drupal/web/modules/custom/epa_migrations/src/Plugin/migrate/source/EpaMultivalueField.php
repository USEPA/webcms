<?php

namespace Drupal\epa_migrations\Plugin\migrate\source;

use Drupal\migrate\Row;
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
 * )
 */
class EpaMultivalueField extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (isset($this->configuration['field'])) {
      $field_table = 'field_revision_' . $this->configuration['field'];

      $query = $this->select($field_table, 'fd')
        ->fields('fd');

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

}
