<?php
namespace Drupal\epa_migrations\Plugin\migrate\source;

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
}
