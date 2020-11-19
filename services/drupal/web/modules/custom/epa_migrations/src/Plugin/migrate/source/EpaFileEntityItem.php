<?php

namespace Drupal\epa_migrations\Plugin\migrate\source;

use Drupal\media_migration\Plugin\migrate\source\d7\FileEntityItem;

/**
 * File Entity Item source plugin.
 *
 * Available configuration keys:
 * - type: (optional) If supplied, this will only return fields
 *   of that particular type.
 *
 * @MigrateSource(
 *   id = "epa_d7_file_entity_item",
 *   source_module = "file_entity",
 * )
 */
class EpaFileEntityItem extends FileEntityItem {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();

    // Remove core's timestamp sorting because this causes problems mixing
    // with our use of fid high water mark. We don't use timestamp high water
    // because it isn't unique, and stops/restarts can result in files being
    // skipped.
    $sort =& $query->getOrderBy();
    unset($sort['f.timestamp']);

    return $query;
  }

}
