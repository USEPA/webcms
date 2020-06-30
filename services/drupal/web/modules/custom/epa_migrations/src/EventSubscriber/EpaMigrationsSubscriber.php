<?php

namespace Drupal\epa_migrations\EventSubscriber;

use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Event\MigrateEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * EPA Migrations event subscriber.
 */
class EpaMigrationsSubscriber implements EventSubscriberInterface {

  /**
   * The drupal_static cache.
   *
   * @var array
   */
  protected $staticCache;

  /**
   * The regex used to match node migrations.
   *
   * @var array
   */
  protected $pregPatternNodeMigrations;

  /**
   * Constructs event subscriber.
   */
  public function __construct() {
    $this->staticCache = &drupal_static('epa_node_migration');

    $this->pregPatternNodeMigrations = '/upgrade_d7_node_.*|upgrade_d7_group_content_node/';
  }

  /**
   * Migrate pre import event handler.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   Migrate import event.
   */
  public function onMigratePreImport(MigrateImportEvent $event) {
    $current_migration = $event->getMigration()->getBaseId();
    if (preg_match($this->pregPatternNodeMigrations, $current_migration)) {
      $this->staticCache = TRUE;
    }
  }

  /**
   * Migrate post import event handler.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   Migrate import event.
   */
  public function onMigratePostImport(MigrateImportEvent $event) {
    $current_migration = $event->getMigration()->getBaseId();
    if (preg_match($this->pregPatternNodeMigrations, $current_migration)) {
      $this->staticCache = FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      MigrateEvents::PRE_IMPORT => 'onMigratePreImport',
      MigrateEvents::POST_IMPORT => 'onMigratePostImport',
    ];
  }

}
