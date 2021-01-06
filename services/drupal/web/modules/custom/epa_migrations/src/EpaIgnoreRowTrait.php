<?php

namespace Drupal\epa_migrations;

use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;

trait EpaIgnoreRowTrait {

  /**
   * Note a row as ignored and return false
   *
   * @param Row $row
   *   The source row
   *
   * @param string $message
   *   Optional message to store on the migration mapping
   *
   * @return false
   */
  public function ignoreRow(Row $row, $message = '') {
    if ($message) {
      $this->idMap->saveMessage(
        $row->getSourceIdValues(),
        $message,
        MigrationInterface::MESSAGE_INFORMATIONAL
      );
    }

    $this->idMap->saveIdMapping($row, [], MigrateIdMapInterface::STATUS_IGNORED);
    $this->currentRow = NULL;
    $this->currentSourceIds = NULL;

    return FALSE;
  }
}
