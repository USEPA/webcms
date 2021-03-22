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
    unset($sort['fm.timestamp']);

    return $query;
  }

  /**
   * {@inheritDoc}
   */
  public function prepareRow($row) {
    // Always include this fragment at the beginning of every prepareRow()
    // implementation, so parent classes can ignore rows.
    if (parent::prepareRow($row) === FALSE) {
      return FALSE;
    }

    // Since we don't have a separate migration to migrate all terms from the
    // Keywords vocabulary on D7, we can't simply assign the tid for media
    // tags in D8. We need to generate the terms during the File Entity
    // migration. To generate, we need a tid and a term name.
    $term_data = [];
    $tids = $row->getSourceProperty('field_keywords');
    if ($tids) {
      foreach ($tids as $tid) {
        $name = $this->select('taxonomy_term_data', 'ttd')
          ->fields('ttd', ['name'])
          ->condition('ttd.tid', $tid['tid'])
          ->execute()
          ->fetchField();

        if ($name) {
          $term_data[] = [
            'tid' => $tid['tid'],
            'name' => $name,
          ];
        }
      }
    }

    $row->setSourceProperty('field_keywords_data', $term_data);

    return parent::prepareRow($row);

  }

}
