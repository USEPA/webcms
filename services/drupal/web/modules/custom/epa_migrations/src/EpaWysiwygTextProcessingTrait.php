<?php

namespace Drupal\epa_migrations;

use DOMDocument;

/**
 * Helpers to reformat/strip inline HTML.
 */
trait EpaWysiwygTextProcessingTrait {

  /**
   * Transform 'related info box' in wysiwyg content.
   *
   * @param string $wysiwyg_content
   *   The content to search and transform inline html.
   *
   * @return string
   *   The original wysiwyg_content with transformed inline html.
   */
  public function processText($wysiwyg_content) {

    $pattern = '/box multi related-info|pagetop|exit-disclaimer|exit-?epa|need Adobe Reader to view|need a PDF reader to view/s';

    $num_matches = preg_match($pattern, $wysiwyg_content);

    if ($num_matches > 0) {
      // Add a temp wrapper around the wysiwyg content.
      $wysiwyg_content = '<?xml encoding="UTF-8"><tempWrapper>' . $wysiwyg_content . '</tempWrapper>';

      // Load the content as a DOMDocument for more powerful transformation.
      $doc = new \DomDocument();
      libxml_use_internal_errors(TRUE);
      $doc->loadHtml($wysiwyg_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOENT);
      libxml_clear_errors();

      // Run the document through the transformation methods.
      $doc = $this->transformRelatedInfoBox($doc);
      $doc = $this->stripPageTopLinks($doc);
      $doc = $this->stripExitEpaLinks($doc);
      $doc = $this->stripPdfDisclaimers($doc);

      // Transform the document back to HTML.
      $wysiwyg_content = $doc->saveHtml();

      // Remove the temp wrapper and encoding from the output.
      return str_replace([
        '<?xml encoding="UTF-8">',
        '<tempWrapper>',
        '</tempWrapper>',
      ], '', $wysiwyg_content);
    }

    return $wysiwyg_content;
  }

  /**
   * Transform Related Info Box.
   *
   * @param \DOMDocument $doc
   *   The document to search and replace.
   *
   * @return \DOMDocument
   *   The document with transformed info boxes.
   */
  private function transformRelatedInfoBox(DOMDocument $doc) {

    // Create a DOM XPath object for searching the document.
    $xpath = new \DOMXPath($doc);

    $related_info_boxes = $xpath->query('//div[contains(@class, "box multi related-info")]');

    if ($related_info_boxes) {
      foreach ($related_info_boxes as $key => $rib_wrapper) {
        // Replace div classes on box wrapper.
        $box_classes = [
          'box multi related-info',
          'right',
          'left',
          'clear-right',
          'clear-left',
        ];

        $box_replacement_classes = [
          'box box--related-info',
          'u-align-right',
          'u-align-left',
          'u-clear-right',
          'u-clear-left',
        ];

        $wrapper_classes = $rib_wrapper->attributes->getNamedItem('class')->value;
        $wrapper_classes = str_replace($box_classes, $box_replacement_classes, $wrapper_classes);
        $rib_wrapper->setAttribute('class', $wrapper_classes);

        // Change child H2 to div and replace classes.
        $h2 = $xpath->query('//h2[contains(@class, "pane-title")]', $rib_wrapper)[0];
        if ($h2) {
          $box_title = $doc->createElement('div', $h2->nodeValue);
          $box_title_classes = $h2->attributes->getNamedItem('class')->value;
          $box_title_classes = str_replace('pane-title', 'box__title', $box_title_classes);
          $box_title->setAttribute('class', $box_title_classes);
          $h2->parentNode->replaceChild($box_title, $h2);
        }

        // Replace div class on pane content.
        $box_content = $xpath->query('//div[contains(@class, "pane-content")]', $rib_wrapper)[0];
        if ($box_content) {
          $box_content_classes = $box_content->attributes->getNamedItem('class')->value;
          $box_content_classes = str_replace('pane-content', 'box__content', $box_content_classes);
          $box_content->setAttribute('class', $box_content_classes);
        }

        // Replace the original element with the modified element in the doc.
        $rib_wrapper->parentNode->replaceChild($rib_wrapper, $related_info_boxes[$key]);
      }
    }

    return $doc;

  }

  /**
   * Strip Top of Page links.
   *
   * @param \DOMDocument $doc
   *   The document to search and replace.
   *
   * @return \DOMDocument
   *   The document with stripped links.
   */
  private function stripPageTopLinks(DOMDocument $doc) {
    // Create a DOM XPath object for searching the document.
    $xpath = new \DOMXPath($doc);

    $page_top_links = $xpath->query('//*[contains(concat(" ", @class, " "), " pagetop ")]');

    if ($page_top_links) {
      foreach ($page_top_links as $link) {
        $link->parentNode->removeChild($link);
      }
    }

    return $doc;
  }

  /**
   * Strip Exit EPA link disclaimers.
   *
   * @param \DOMDocument $doc
   *   The document to search and replace.
   *
   * @return \DOMDocument
   *   The document with stripped links.
   */
  private function stripExitEpaLinks(DOMDocument $doc) {
    // Create a DOM XPath object for searching the document.
    $xpath = new \DOMXPath($doc);

    // Links that use the exit-disclaimer class.
    $exit_epa_links = $xpath->query('//*[contains(concat(" ", @class, " "), " exit-disclaimer ") or contains(@href, "exit-epa") or contains(@href, "exitepa")]');

    if ($exit_epa_links) {
      foreach ($exit_epa_links as $link) {
        $link->parentNode->removeChild($link);
      }
    }

    return $doc;
  }

  /**
   * Strip PDF disclaimers.
   *
   * @param \DOMDocument $doc
   *   The document to search and replace.
   *
   * @return \DOMDocument
   *   The document with stripped disclaimers.
   */
  private function stripPdfDisclaimers(DOMDocument $doc) {
    // Create a DOM XPath object for searching the document.
    $xpath = new \DOMXPath($doc);

    // Elements that include the PDF disclaimer.
    $pdf_disclaimer_elements = $xpath->query('//*[contains(text(), "need Adobe Reader to view") or contains(text(), "need a PDF reader to view")]');

    if ($pdf_disclaimer_elements) {
      foreach ($pdf_disclaimer_elements as $element) {
        $element->parentNode->removeChild($element);
      }
    }

    return $doc;
  }

}
