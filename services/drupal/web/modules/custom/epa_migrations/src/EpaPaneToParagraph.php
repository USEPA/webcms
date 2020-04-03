<?php

namespace Drupal\epa_migrations;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create an HTML Paragraph from a node_content pane.
 */
class EpaPaneToParagraph implements EpaPaneToParagraphInterface, ContainerInjectionInterface {

  use EpaBoxWrapperTrait;
  use EpaCreateParagraphsTrait;

  /**
   * The drupal_7 database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $d7Connection;

  /**
   * Constructs an EpaPaneToParagraph object.
   *
   * @param Drupal\Core\Database\Connection $database
   *   The injected database service.
   */
  public function __construct(Connection $database) {
    $this->d7Connection = $database;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('epa_migrations.epa_pane_to_paragraph')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function createParagraph($row, $record, $configuration) {
    // Child classes will need to implement the transformation logic.
    return NULL;
  }

}
