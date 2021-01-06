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
   * @param $deleted
   * @param bool $consolidate_aliases Marks all other active (not-flagged-as-deleted) alias records for this entity as deleted
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   */
  public function log($entity_type, $id, $alias, $deleted, $consolidate_aliases = FALSE) {

    // The transaction opens here.
    $transaction = $this->connection->startTransaction();
    try {
      if ($consolidate_aliases) {
        $aliases = $this->connection
          ->select($this->table)
          ->fields('epa_content_tracker', ['alias'])
          ->condition('entity_id', $id)
          ->condition('entity_type', $entity_type)
          ->condition('deleted', 0)
          ->execute()
          ->fetchCol();

        foreach ($aliases as $alias_to_consolidate) {
          $this->log($entity_type, $id, $alias_to_consolidate, self::DELETED);
        }
      }

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
          'changed' => time(),
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

  public function entityIsTracked(EntityInterface $entity) {
    // Only record changes to document media entities and nodes
    $entity_type = $entity->getEntityTypeId();
    return ($entity_type === 'node' || ($entity_type === 'media' && $entity->bundle() === 'document'));
  }

  public function update(EntityInterface $entity) {
    if (!$this->entityIsTracked($entity)) {
      return;
    }

    switch($entity->getEntityTypeId()) {
      case 'node':
        $alias = $entity->toUrl()->toString();
        break;
      case 'media':
        $alias = $this->getAliasFromMedia($entity);
        break;
    }

    if (!empty($alias)) {
      $this->log($entity->getEntityTypeId(),$entity->id(), $alias, self::UPDATED, TRUE);
    }
  }

  public function delete(EntityInterface $entity) {
    if (!$this->entityIsTracked($entity)) {
      return;
    }

    // Check to see if there is a non-deleted existing alias for this entity. If
    // not, then it means that we never recorded an insert/update (e.g., because
    // it was always private or never published), so we shouldn't record a
    // delete event either.
    $original_alias = $this->getTrackerAliasForEntity($entity);
    if (empty($original_alias)) {
      return;
    }

    $this->log($entity->getEntityTypeId(), $entity->id(), $original_alias,self::DELETED, TRUE);
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
      return $file->getFileUri();
    }
  }

  /**
   * Determines the alias for a given entity, assuming it exists. Returns FALSE if no
   * alias has been recorded.
   *
   * @param EntityInterface $entity
   * @return string|bool
   */
  public function getTrackerAliasForEntity(EntityInterface $entity) {
    return $this->connection
      ->select($this->table)
      ->fields('epa_content_tracker', ['alias'])
      ->condition('entity_id', $entity->id())
      ->condition('entity_type', $entity->getEntityType()->id())
      ->condition('deleted', 0)
      ->execute()
      ->fetchField();
  }



  public function mediaUpdate(EntityInterface $media) {
    if (!$this->entityIsTracked($media)) {
      return;
    }

    // Record a delete if this file is private. Otherwise, record an update
    $current_privacy = $media->field_limit_file_accessibility->value;
    if ($current_privacy) {
      $this->delete($media);
    } else {
      $this->update($media);
    }
  }
}
