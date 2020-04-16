<?php

namespace Drupal\epa_migrations\Plugin\migrate\source;

use Drupal\media_migration\Plugin\migrate\source\d7\FileEntityItem;

/**
 * EPA File Entity Item source plugin. Limit to files that are used.
 *
 * Available configuration keys:
 * - type: (optional) If supplied, this will only return fields
 *   of that particular type.
 *
 * @MigrateSource(
 *   id = "epa_d7_file_entity_item",
 * )
 */
class EpaFileEntityItem extends FileEntityItem {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Duplicating the parent query here because they didn't use table aliases
    // when specifying field condtions.
    $query = $this->select('file_managed', 'f')
      ->fields('f')
      ->orderBy('f.timestamp');

    // Filter by type, if configured.
    if (isset($this->configuration['type'])) {
      $op = is_array($this->configuration['type']) ? 'IN' : '=';
      $query->condition('f.type', $this->configuration['type'], $op);
    }

    // Filter by URI prefix if specified. Default to 'public://'.
    if (isset($this->configuration['uri_prefix'])) {
      $query->condition('f.uri', $this->configuration['uri_prefix'] . '%', 'LIKE');
    }
    else {
      $query->condition('f.uri', 'public://%', 'LIKE');
    }

    // Filter by files that are used.
    $query->innerJoin('file_usage', 'fu', 'f.fid = fu.fid');
    $query->distinct();

    return $query;
  }

}
