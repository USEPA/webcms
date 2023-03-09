<?php

namespace Drupal\epa_s3fs;

use Drupal\s3fs\S3fsService;

/**
 * Defines a S3fsService service.
 */
class EpaS3fsService extends S3fsService {

  /**
   * {@inheritdoc}
   */
  public function setupTempTable() {
    parent::setupTempTable();
    module_load_install('epa_s3fs');
    epa_s3fs_fix_table_indexes('s3fs_file_temp');
  }

}
