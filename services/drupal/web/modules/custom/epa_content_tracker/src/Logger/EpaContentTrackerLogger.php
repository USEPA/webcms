<?php

namespace Drupal\epa_content_tracker\Logger;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\file\Entity\File;
use Exception;

/**
 * Class EpaContentTrackerLogger
 * @package Drupal\epa_content_tracker\Logger
 * Logs content url aliases to the epa_content_tracker table.
 */
class EpaContentTrackerLogger {
  const DELETED = 1;
  const UPDATED = 0;

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
      // Clear all records corresponding to the given $alias, which the external system
      // treats as a unique key. The entity_type and entity_id columns are used primarily
      // for querying on the Drupal end at the XML endpoints.
      $this->connection->delete($this->table)
        ->condition('alias', $alias)
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

  /**
   * Determines the the alias for a media entity from its file's URL.
   *
   * @param EntityInterface $entity
   * @return string|null
   */
  public function getAliasFromMedia(EntityInterface $media) {
    $file_id = $media->field_media_file[0]->target_id;
    $file = File::load($file_id);

    if ($file !== NULL) {
      return $file->createFileUrl();
    }
  }

  /**
   * Determines the alias for a given entity, assuming it exists. Returns FALSE if no
   * alias has been recorded.
   *
   * @param EntityInterface $entity
   * @return string|bool
   */
  public function getAliasForEntity(EntityInterface $entity) {
    return Database::getConnection()
      ->select('epa_content_tracker')
      ->fields('epa_content_tracker', ['alias'])
      ->condition('entity_id', $entity->id())
      ->condition('entity_type', $entity->getEntityType()->id())
      ->condition('deleted', 0)
      ->execute()
      ->fetchField();
  }

  /**
   * @param EntityInterface $entity
   * @param bool $deleted
   * @param string $alias_override Optional override to specify the alias for this entity.
   */
  public function mediaLog(EntityInterface $media, $deleted, $alias_override = null) {
    $alias = $alias_override;

    // Default to generating an alias if there was no explicit one set.
    if (empty($alias)) {
      $alias = $this->getAliasFromMedia($media);
    }

    $this->log($media->getEntityType()->id(), $media->id(), $alias, $media->getChangedTime(), $deleted);
  }

  public function mediaInsert(EntityInterface $media) {
    // Don't record changes to non-document media entities
    if ($media->bundle() !== 'document') {
      return;
    }

    // If the user specified that the file attached to this media is private,
    // then don't log any changes.
    if ($media->field_limit_file_accessibility->value) {
      return;
    }

    $this->mediaLog($media, self::UPDATED);
  }

  public function mediaDelete(EntityInterface $media) {
    // Don't record changes to non-document media entities
    if ($media->bundle() !== 'document') {
      return;
    }

    // Check to see if there is an existing alias for this entity. If not, then it means
    // that we never recorded an insert/update (e.g., because it was always private), so
    // we shouldn't record a delete event either.
    $original_alias = $this->getAliasForEntity($media);
    if (empty($original_alias)) {
      return;
    }

    $this->mediaLog($media, self::DELETED);
  }

  public function mediaUpdate(EntityInterface $media) {
    // As above, don't record changes to non-document entities
    if ($media->bundle() !== 'document') {
      return;
    }

    $original = $media->original;

    $current_alias = $this->getAliasFromMedia($media);
    $original_alias = $this->getAliasForEntity($original);

    // If the file path changed, then we need to record a delete for the previous alias
    if ($original_alias && $current_alias !== $original_alias) {
      $this->mediaLog($original, self::DELETED, $original_alias);
    }

    // Record a delete if this file is private. Otherwise, record an update to trigger
    // reindexing.
    $current_privacy = $media->field_limit_file_accessibility->value;
    if ($current_privacy) {
      $this->mediaDelete($media);
    } else {
      $this->mediaInsert($media);
    }
  }
}
