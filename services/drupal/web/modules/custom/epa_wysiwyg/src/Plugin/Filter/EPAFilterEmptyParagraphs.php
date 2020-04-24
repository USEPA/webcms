<?php

namespace Drupal\epa_wysiwyg\Plugin\Filter;

use Drupal\filter\Annotation\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Class EPAFilterEmptyParagraphs
 * @Filter(
 *   id = "epa_filter_empty_paragraphs",
 *   title = @Translation("Filter empty paragraphs"),
 *   description = @Translation("Removes empty paragraphs from html output."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 * )
 * @package Drupal\epa_wysiwyg\Plugin\Filter
 */
class EPAFilterEmptyParagraphs extends FilterBase {

  /**
   * Performs the filter processing.
   *
   * @param string $text
   *   The text string to be filtered.
   * @param string $langcode
   *   The language code of the text to be filtered.
   *
   * @return \Drupal\filter\FilterProcessResult
   *   The filtered text, wrapped in a FilterProcessResult object, and possibly
   *   with associated assets, cacheability metadata and placeholders.
   *
   * @see \Drupal\filter\FilterProcessResult
   */
  public function process($text, $langcode) {
    $regex = '/<p( [^>]*)?>(&nbsp;|\s)*<\/p>/ui';
    $text = preg_replace($regex, '', $text);
    return new FilterProcessResult($text);
  }

}
