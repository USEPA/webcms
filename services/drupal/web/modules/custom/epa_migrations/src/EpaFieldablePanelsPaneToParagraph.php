<?php

namespace Drupal\epa_migrations;

use Drupal\paragraphs\Entity\Paragraph;

/**
 * Create paragraphs from a fieldable_panels_pane (fpp) pane.
 */
class EpaFieldablePanelsPaneToParagraph extends EpaPaneToParagraph {

  /**
   * {@inheritDoc}
   */
  public function createParagraph($row, $record, $configuration) {
    // Extract either the fpid or vid. Data is stored as 'fpid:%' or 'vid:%'.
    list($id_type, $id) = explode(':', $record['subtype'], 2);

    if (isset($id_type) && isset($id)) {
      $pane = $this->d7Connection->select('fpp', 'fieldable_panels_pane')
        ->fields('fpp')
        ->condition("fpp.{$id_type}", $id, '=')
        ->execute()
        ->fetchObject();

      switch ($pane->bundle) {
        case 'fieldable_panels_pane':
          break;

        case 'link_list':
          break;

        case 'node_list':
          break;

        case 'slideshow':
          break;

        case 'map':
          break;

        default:
          return NULL;
      }
    }

    // Get the fpp bundle.
    // $bundle =
    // $body_field = $row->get('body');

    // $html_paragraph = Paragraph::create(['type' => 'html']);
    // $html_paragraph->set('field_body', $body_field);
    // $html_paragraph->isNew();
    // $html_paragraph->save();

    // return [
    //   'target_id' => $html_paragraph->id(),
    //   'target_revision_id' => $html_paragraph->getRevisionId(),
    // ];

    return NULL;
  }

}
