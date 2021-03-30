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
      switch ($id_type) {
        case 'fpid':
          // If we are working with a 'fpid' then the pane is reusable. Let's
          // get the vid for this fpid from the current revision table to ensure
          // we're getting data for the current version of the reusable pane.
          $fpid = $id;
          $vid = $this->d7Connection->select('fieldable_panels_panes', 'fpp')
            ->fields('fpp', ['vid'])
            ->condition('fpp.fpid', $id)
            ->execute()
            ->fetchCol();
          $vid = array_pop($vid);
          break;

        case 'vid':
          // If we are working with a 'vid' then the pane is not reusable. Let's
          // get the fpid for this vid from the revision table.
          $fpid = $this->d7Connection->select('fieldable_panels_panes_revision', 'fppr')
            ->fields('fppr', ['fpid'])
            ->condition('fppr.vid', $id)
            ->execute()
            ->fetchCol();
          $fpid = array_pop($fpid);
          $vid = $id;
          break;
      }

      // Get the pane.
      $pane = $this->d7Connection->select('fieldable_panels_panes', 'fpp')
        ->fields('fpp')
        ->condition('fpp.fpid', $fpid, '=')
        ->execute()
        ->fetchObject();

      $pane_revision = $this->d7Connection->select('fieldable_panels_panes_revision', 'fppr')
        ->fields('fppr')
        ->condition('fppr.vid', $vid, '=')
        ->execute()
        ->fetchObject();
      // Determine if this is a reusable pane and whether it already exists.
      if ($pane->reusable) {
        $label = $fpid . ': ';
        // Set a default label of fpid if there is no admin_title or title.
        $label .= $pane->admin_title ?: $pane_revision->title;

        $library_item = $this->getParagraphLibraryItem($label, $this->entityTypeManager);

        // If the library item exists, return a from_library paragraph.
        if ($library_item) {
          return $this->createFromLibraryParagraph($library_item, $this->entityTypeManager);
        }
      }

      // Either this pane is not reusable or no library item paragraph exists
      // for this item, yet.
      // Extract values that apply to all panes.
      // Get box style from the settings array if it's there. Otherwise, use the
      // value in configuration.
      $style = unserialize($record['style']);
      $box_style = $style['settings']['epa_box_style'] ?? $configuration['box_style'] ?? 'none';

      $title = $pane_revision->title;

      $paragraph = NULL;

      // Process each pane type.
      // Since we ensured we have the correct vid for this pane, we can use the
      // revision data tables to get field data.
      switch ($pane->bundle) {
        case 'fieldable_panels_pane':
          $body_field_query = $this->d7Connection->select('field_revision_field_epa_fpp_body', 'fpp_body')
            ->condition('fpp_body.revision_id', $vid, '=');
          $body_field_query->addField('fpp_body', 'field_epa_fpp_body_value', 'value');
          $body_field_query->addField('fpp_body', 'field_epa_fpp_body_format', 'format');

          // Transform body field content.
          $body_field = $body_field_query->execute()->fetchAssoc();

          if (isset($body_field['value'])) {
            $body_field['value'] = $this->transformWysiwyg($body_field['value'], $this->entityTypeManager, $record['is_skinny_pane']);

            // Perform text processing to update/remove inline code.
            $body_field['value'] = $this->processText($body_field['value']);

            $paragraph = $this->addBoxWrapper(
              [
                $this->createHtmlParagraph($body_field),
              ],
              $title,
              $box_style
            );
          }
          break;

        case 'link_list':
          $body_field_query = $this->d7Connection->select('field_revision_field_epa_fpp_body', 'fpp_body')
            ->condition('fpp_body.revision_id', $vid, '=');
          $body_field_query->addField('fpp_body', 'field_epa_fpp_body_value', 'value');
          $body_field_query->addField('fpp_body', 'field_epa_fpp_body_format', 'format');

          // Transform body field content.
          $body_field = $body_field_query->execute()->fetchAssoc() ?: [];
          $body_field['value'] ? $body_field['value'] = $this->transformWysiwyg($body_field['value'], $this->entityTypeManager, $record['is_skinny_pane']) : FALSE;

          // Perform text processing to update/remove inline code.
          $body_field['value'] ? $body_field['value'] = $this->processText($body_field['value']) : FALSE;

          $links_query = $this->d7Connection->select('field_revision_field_epa_link_list_links', 'fpp_links')
            ->condition('fpp_links.revision_id', $vid, '=');
          $links_query->addField('fpp_links', 'field_epa_link_list_links_url', 'link');
          $links_query->addField('fpp_links', 'field_epa_link_list_links_title', 'title');
          $links_query->orderBy('fpp_links.delta');

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
          $body_field_query = $this->d7Connection->select('field_revision_field_epa_fpp_body', 'fpp_body')
            ->condition('fpp_body.revision_id', $vid, '=');
          $body_field_query->addField('fpp_body', 'field_epa_fpp_body_value', 'value');
          $body_field_query->addField('fpp_body', 'field_epa_fpp_body_format', 'format');

          // Transform body field content.
          $body_field = $body_field_query->execute()->fetchAssoc() ?: [];
          $body_field['value'] ? $body_field['value'] = $this->transformWysiwyg($body_field['value'], $this->entityTypeManager, $record['is_skinny_pane']) : FALSE;

          // Perform text processing to update/remove inline code.
          $body_field['value'] ? $body_field['value'] = $this->processText($body_field['value']) : FALSE;

          $links_query = $this->d7Connection->select('field_revision_field_epa_node_list_ref', 'fpp_links')
            ->condition("fpp_links.revision_id", $vid, '=');
          $links_query->addField('fpp_links', 'field_epa_node_list_ref_target_id', 'entity');
          $links_query->orderBy('fpp_links.delta');

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
