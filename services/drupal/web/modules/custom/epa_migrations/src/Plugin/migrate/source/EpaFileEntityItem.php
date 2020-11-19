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

    // Don't use timestamp highwater because it isn't unique, and stops/restarts
    // can result in files being skipped. Remove the parent's sort and replace
    // with fid.
    $sort =& $query->getOrderBy();
    unset($sort['f.timestamp']);
    // Use fid highwater mark
    $query->orderBy('f.fid');

    return $query;
  }

}
