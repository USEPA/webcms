<?php
namespace Drupal\epa_migrations\Plugin\migrate\source;

use Drupal\epa_migrations\EpaIgnoreRowTrait;
use Drupal\migrate\Row;
use Drupal\redirect\Plugin\migrate\source\d7\PathRedirect;

/**
 * EPA Drupal 7 path redirect source from database.
 *
 * @MigrateSource(
 *   id = "epa_d7_path_redirect",
 *   source_module = "redirect"
 * )
 */
class EpaPathRedirect extends PathRedirect {
  use EpaIgnoreRowTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Select path redirects.
    // Do not bring over redundant redirects that just point at a node ID that
    // already has the redirect source set as the alias for the node.
    $query = $this->select('redirect', 'p')->fields('p');
    $query->leftJoin('url_alias', 'a', 'a.source = p.redirect');
    $query->where('a.alias != p.source OR a.source IS NULL');
    $query->distinct();
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // Ignore this redirect if a path alias already exists with the same source
    // and destination.
    //
    // Some nodes get an updated URL Alias during the 'Set Latest Revision'
    // step of the migration when their URL Alias gets re-generated. This
    // typically only happens when a URL alias was made unique in D7 by
    // appending '-0' or '-1', etc. and the node that used the original URL
    // Alias wasn't migrated, thereby freeing up the original alias in D9.
    $redirect = $row->getSourceProperty('redirect');
    if (strpos($redirect, 'node/') === 0) {
      $d7_node_path = '/' . $redirect;
      $d7_source_as_alias = '/' . $row->getSourceProperty('source');
      $path_alias = $this->entityTypeManager->getStorage('path_alias')->loadByProperties(['alias' => $d7_source_as_alias]);
      foreach ($path_alias as $pa) {
        if ($pa->getPath() == $d7_node_path) {
          $this->ignoreRow($row, "Skipping unneeded redirect because the node's alias was updated by pathauto: '$d7_node_path'");
        }
      }
    }
    return parent::prepareRow($row);
  }

}
