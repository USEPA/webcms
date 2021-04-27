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
  use EpaWysiwygTextProcessingTrait;

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

    $body_field = $configuration['html_body'];

    // Extract media that uses the block_header view mode.
    $split_content = $this->extractBlockHeader($body_field['value']);

    // Convert D7 media to D8 media.
    $body_field['value'] = $this->transformWysiwyg($split_content['wysiwyg_content'], $this->entityTypeManager, $record['is_skinny_pane']);

    // Perform text processing to update/remove inline code.
    $body_field['value'] = $this->processText($body_field['value'], 'box');

    // Get box style from the settings array if it's there. Otherwise, use the
    // value in configuration.
    $style = unserialize($record['style']);
    $box_style = $style['settings']['epa_box_style'] ?? $configuration['box_style'] ?? 'none';

    // Create an html paragraph and wrap it in a box.
    return $this->addBoxWrapper(
      [
        $this->createHtmlParagraph($body_field, $this->entityTypeManager),
      ],
      $configuration['override_title_text'],
      $box_style,
      $split_content['block_header_img'],
      $split_content['block_header_url'],
    );
  }

}
