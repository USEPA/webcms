<?php

namespace Drupal\epa_migrations;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create a Box paragraph from an epa_core_html pane.
 */
class EpaCoreHtmlPaneToParagraph extends EpaPaneToParagraph {
  use EpaMediaWysiwygTransformTrait;

  /**
   * The drupal_7 database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $d7Connection;

  /**
   * The entity type manager server.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructs an EpaPaneToParagraph object.
   *
   * @param Drupal\Core\Database\Connection $database
   *   The injected database service.
   * @param Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(Connection $database, EntityTypeManager $entity_type_manager) {
    $this->d7Connection = $database;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('epa_migrations.epa_pane_to_paragraph'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function createParagraph($row, $record, $configuration) {

    // Transform body field content.
    $body_field = $configuration['html_body'];
    $body_field['value'] = $this->transformWysiwyg($body_field['value'], $this->entityTypeManager);

    // Create an html paragraph and wrap it in a box.
    return $this->addBoxWrapper(
      [
        $this->createHtmlParagraph($body_field, $this->entityTypeManager),
      ],
      $configuration['override_title_text'],
      $configuration['box_style']
    );
  }

}
