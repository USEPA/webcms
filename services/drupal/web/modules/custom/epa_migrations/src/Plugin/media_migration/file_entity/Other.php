<?php

namespace Drupal\epa_migrations\Plugin\media_migration\file_entity;

use Drupal\media_migration\Plugin\media_migration\file_entity\FileBase;
use Drupal\Core\Database\Connection;
use Drupal\migrate\Row;

/**
 * Other media migration plugin for other media.
 *
 * @FileEntityDealer(
 *   id = "other",
 *   types = {"other"},
 *   destination_media_type_id_base = "other",
 *   destination_media_source_plugin_id = "epa_d7_file_entity_item"
 * )
 */
class Other extends FileBase {

  /**
   * {@inheritdoc}
   */
  public function getDestinationMediaTypeSourceFieldLabel() {
    return 'Other file';
  }

  /**
   * {@inheritdoc}
   */
  public function getDestinationMediaTypeLabel() {
    return 'Other';
  }

}
