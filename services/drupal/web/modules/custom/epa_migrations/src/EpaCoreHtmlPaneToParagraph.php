<?php

namespace Drupal\epa_migrations;

/**
 * Create a Box paragraph from an epa_core_html pane.
 */
class EpaCoreHtmlPaneToParagraph extends EpaPaneToParagraph {

  /**
   * {@inheritDoc}
   */
  public function createParagraph($row, $record, $configuration) {

    // Create an html paragraph and wrap it in a box.
    return $this->addBoxWrapper(
      [
        $this->createHtmlParagraph($configuration['html_body']),
      ],
      $configuration['override_title_text'],
      $configuration['box_style']
    );
  }

}
