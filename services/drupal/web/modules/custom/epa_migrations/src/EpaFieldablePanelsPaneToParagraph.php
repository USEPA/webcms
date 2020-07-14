<?php

namespace Drupal\epa_migrations;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create paragraphs from a fieldable_panels_pane (fpp) pane.
 */
class EpaFieldablePanelsPaneToParagraph extends EpaPaneToParagraph {
  use EpaMediaWysiwygTransformTrait;
  use EpaWysiwygTextProcessingTrait;

  /**
   * The drupal_7 database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $d7Connection;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructs an EpaPaneToParagraph object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The injected database service.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity_type manager.
   */
  public function __construct(Connection $database, EntityTypeManager $entityTypeManager) {
    $this->d7Connection = $database;
    $this->entityTypeManager = $entityTypeManager;
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
    // Extract either the fpid or vid. Data is stored as 'fpid:%' or 'vid:%'.
    list($id_type, $id) = explode(':', $record['subtype'], 2);

    if (isset($id_type) && isset($id)) {
      // Get the pane.
      $pane = $this->d7Connection->select('fieldable_panels_panes', 'fpp')
        ->fields('fpp')
        ->condition("fpp.{$id_type}", $id, '=')
        ->execute()
        ->fetchObject();

      // Determine if this is a reusable pane and whether it already exists.
      if ($pane->reusable) {
        // Set a default label if there is no title or admin_title.
        $label = $pane->fpid;

        if ($pane->title) {
          $label = $pane->title;
        }
        elseif ($pane->admin_title) {
          $label = $pane->admin_title;
        }

        $library_item = $this->getParagraphLibraryItem($label, $this->entityTypeManager);

        // If the library item exists, return a from_library paragraph.
        if ($library_item) {
          return $this->createFromLibraryParagraph($library_item, $this->entityTypeManager);
        }
      }

      // Either this pane is not reusable or no library item paragraph exists
      // for this item, yet.
      // Extract values that apply to all panes.
      $box_style = $configuration['settings']['epa_box_style'] ?? 'none';
      $title = $pane->title;

      // Convert id_type so we can extract data from pane field tables.
      $id_type = $id_type == 'fpid' ? 'entity_id' : 'revision_id';

      $paragraph = NULL;

      // Process each pane type.
      switch ($pane->bundle) {
        case 'fieldable_panels_pane':
          $body_field_query = $this->d7Connection->select('field_data_field_epa_fpp_body', 'fpp_body')
            ->condition("fpp_body.{$id_type}", $id, '=');
          $body_field_query->addField('fpp_body', 'field_epa_fpp_body_value', 'value');
          $body_field_query->addField('fpp_body', 'field_epa_fpp_body_format', 'format');

          // Transform body field content.
          $body_field = $body_field_query->execute()->fetchAssoc();
          $body_field['value'] ? $body_field['value'] = $this->transformWysiwyg($body_field['value'], $this->entityTypeManager) : FALSE;

          // Perform text processing to update/remove inline code.
          $body_field['value'] ? $body_field['value'] = $this->processText($body_field['value']) : FALSE;

          $paragraph = $this->addBoxWrapper(
            [
              $this->createHtmlParagraph($body_field),
            ],
            $title,
            $box_style
          );
          break;

        case 'link_list':
          $body_field_query = $this->d7Connection->select('field_data_field_epa_fpp_body', 'fpp_body')
            ->condition("fpp_body.{$id_type}", $id, '=');
          $body_field_query->addField('fpp_body', 'field_epa_fpp_body_value', 'value');
          $body_field_query->addField('fpp_body', 'field_epa_fpp_body_format', 'format');

          // Transform body field content.
          $body_field = $body_field_query->execute()->fetchAssoc() ?: [];
          $body_field['value'] ? $body_field['value'] = $this->transformWysiwyg($body_field['value'], $this->entityTypeManager) : FALSE;

          // Perform text processing to update/remove inline code.
          $body_field['value'] ? $body_field['value'] = $this->processText($body_field['value']) : FALSE;

          $links_query = $this->d7Connection->select('field_data_field_epa_link_list_links', 'fpp_links')
            ->condition("fpp_links.{$id_type}", $id, '=');
          $links_query->addField('fpp_links', 'field_epa_link_list_links_url', 'link');
          $links_query->addField('fpp_links', 'field_epa_link_list_links_title', 'title');

          $links = $links_query->execute()->fetchAll();

          $paragraph = $this->addBoxWrapper(
            [
              $this->createHtmlParagraph($body_field),
              $this->createLinkListParagraph('uri', $links),
            ],
            $title,
            $box_style
          );
          break;

        case 'node_list':
          $body_field_query = $this->d7Connection->select('field_data_field_epa_fpp_body', 'fpp_body')
            ->condition("fpp_body.{$id_type}", $id, '=');
          $body_field_query->addField('fpp_body', 'field_epa_fpp_body_value', 'value');
          $body_field_query->addField('fpp_body', 'field_epa_fpp_body_format', 'format');

          // Transform body field content.
          $body_field = $body_field_query->execute()->fetchAssoc() ?: [];
          $body_field['value'] ? $body_field['value'] = $this->transformWysiwyg($body_field['value'], $this->entityTypeManager) : FALSE;

          // Perform text processing to update/remove inline code.
          $body_field['value'] ? $body_field['value'] = $this->processText($body_field['value']) : FALSE;

          $links_query = $this->d7Connection->select('field_data_field_epa_node_list_ref', 'fpp_links')
            ->condition("fpp_links.{$id_type}", $id, '=');
          $links_query->addField('fpp_links', 'field_epa_node_list_ref_target_id', 'entity');

          $links = $links_query->execute()->fetchAll();

          $paragraph = $this->addBoxWrapper(
            [
              $this->createHtmlParagraph($body_field),
              $this->createLinkListParagraph('entity', $links),
            ],
            $title,
            $box_style
          );
          break;

        case 'slideshow':
          break;

        case 'map':
          break;

        default:
          break;
      }

      // If this pane is reusable, create a library item from the paragraph that
      // was created for this pane. Return a from_library paragraph that
      // references this library item.
      if ($pane->reusable) {
        $library_item = $this->createParagraphLibraryItem($label, $paragraph, $this->entityTypeManager);
        return $this->createFromLibraryParagraph($library_item, $this->entityTypeManager);
      }

      return $paragraph;
    }
  }

}
