<?php

namespace Drupal\epa_migrations;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create a Box paragraph from panes that contain lists of links.
 *
 * Convert epa_core_link_list_pane and epa_core_node_link panes to link_list
 * paragraphs.
 */
class EpaCoreListPaneToParagraph extends EpaPaneToParagraph {
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

    $body_field = $configuration['node_list_body'] ?? $configuration['link_list_body'];

    // Extract media that uses the block_header view mode.
    $split_content = $this->extractBlockHeader($body_field['value']);

    // Convert D7 media to D8 media.
    $body_field['value'] = $this->transformWysiwyg($split_content['wysiwyg_content'], $this->entityTypeManager, $record['is_skinny_pane']);

    // Perform text processing to update/remove inline code.
    $body_field['value'] = $this->processText($body_field['value'], 'box');

    $link_type = isset($configuration['node_field']) ? 'entity' : 'uri';
    $links = $link_type == 'entity' ? $configuration['node_field'] : $configuration['link_field'];

    // Get box style from the settings array if it's there. Otherwise, use the
    // value in configuration.
    $style = unserialize($record['style']);
    $box_style = $style['settings']['epa_box_style'] ?? $configuration['box_style'] ?? 'none';

    // Wrap the html and link_list paragraphs in a box.
    return $this->addBoxWrapper(
      [
        $this->createHtmlParagraph($body_field),
        $this->createLinkListParagraph($link_type, $links),
      ],
      $configuration['title'] ?? $configuration['override_title_text'] ?? '',
      $box_style,
      $split_content['block_header_img'],
      $split_content['block_header_url'],
    );

  }

}
