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
   * The list of epa node migrations to act on.
   *
   * @var array
   */
  protected $nodeMigrations;

  /**
   * Constructs event subscriber.
   */
  public function __construct() {
    $this->staticCache = &drupal_static('epa_node_migration');

    $this->nodeMigrations = [
      'upgrade_d7_node_document_panelizer' => TRUE,
      'upgrade_d7_node_document' => TRUE,
      'upgrade_d7_node_event' => TRUE,
      'upgrade_d7_node_faq' => TRUE,
      'upgrade_d7_node_news_release' => TRUE,
      'upgrade_d7_node_page_panelizer' => TRUE,
      'upgrade_d7_node_page' => TRUE,
      'upgrade_d7_node_public_notice' => TRUE,
      'upgrade_d7_node_regulation' => TRUE,
      'upgrade_d7_node_web_area_panelizer' => TRUE,
      'upgrade_d7_node_web_area' => TRUE,
    ];
  }

  /**
   * Migrate pre import event handler.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   Migrate import event.
   */
  public function onMigratePreImport(MigrateImportEvent $event) {
    $current_migration = $event->getMigration()->getBaseId();
    if (isset($this->nodeMigrations[$current_migration])) {
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
    if (isset($this->nodeMigrations[$current_migration])) {
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
