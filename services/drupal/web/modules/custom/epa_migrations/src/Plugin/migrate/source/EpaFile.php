<?php

namespace Drupal\epa_migrations\Plugin\migrate\source;

use Drupal\file\Plugin\migrate\source\d7\File;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * EPA Drupal 7 file source from database. Limit to files that are used.
 *
 * @MigrateSource(
 *   id = "epa_d7_file",
 *   source_module = "file"
 * )
 */
class EpaFile extends File {


  /**
   * {@inheritDoc}
   */
  protected $batchSize = 1000;

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    // Customize the publicPath mapping since we're using production. Removed
    // the `$this->getVariable()` calls since the D7 variables table doesn't
    // include these variables. See `parent::initializeIterator()`.
    $this->publicPath = 'sites/production/files';
    $this->privatePath = NULL;
    $this->temporaryPath = '/tmp';

    // Call our grandparent's initailzeIterator(). Calling our parent's iterator
    // would overwrite the changes made above.
    return SqlBase::initializeIterator();
  }

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

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    if (!parent::prepareRow($row)) {
      return FALSE;
    }

    // filename is just based on the uri
    $row->setSourceProperty('filename', basename($row->getSourceProperty('uri')));
    return TRUE;
  }

}
