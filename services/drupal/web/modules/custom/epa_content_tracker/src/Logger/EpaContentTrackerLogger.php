<?php

namespace Drupal\epa_content_tracker\Logger;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseException;
use Exception;

/**
 * Class EpaContentTrackerLogger
 * @package Drupal\epa_content_tracker\Logger
 * Logs content url aliases to the epa_content_tracker table.
 */
class EpaContentTrackerLogger {

  /**
   * The database connection object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  protected $table;

  /**
   * EpaContentTrackerLogger constructor.
   */
  public function __construct() {
    $connection = Database::getConnection();

    $this->connection = $connection;
    $this->table = 'epa_content_tracker';
  }

  /**
   * @param $entity_type
   * @param $id
   * @param $alias
   * @param $changed
   * @param $deleted
   * @return \Drupal\Core\Database\StatementInterface|int|null
   * @throws \Exception
   */
  public function log($entity_type, $id, $alias, $changed, $deleted) {

    // The transaction opens here.
    $transaction = $this->connection->startTransaction();
    try {
      $this->connection->delete($this->table)
        ->condition('entity_type', $entity_type)
        ->condition('entity_id', $id)
        ->execute();

      $id = $this->connection->insert($this->table)
        ->fields([
          'entity_type' => $entity_type,
          'entity_id' => $id,
          'alias' => $alias,
          'changed' => $changed,
          'deleted' => $deleted
        ])
        ->execute();
      return $id;
    }
    catch (Exception $e) {
      $transaction->rollBack();
      watchdog_exception('EpaContentTracker', $e);
    }
    return NULL;
  }

  /**
   * @param $entity_type
   * @param $id
   * @param $alias
   * @param $changed
   * @throws \Exception
   */
  public function insert($entity_type, $id, $alias, $changed) {
    $deleted = 0;
    $this->log($entity_type, $id, $alias, $changed, $deleted);
  }

  /**
   * @param $entity_type
   * @param $id
   * @param $alias
   * @param $changed
   * @throws \Exception
   */
  public function delete($entity_type, $id, $alias, $changed) {
    $deleted = 1;
    $this->log($entity_type, $id, $alias, $changed, $deleted);
  }

}
