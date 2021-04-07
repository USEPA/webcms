<?php

namespace Drupal\epa_wysiwyg\Plugin\Filter;

use Drupal\filter\Annotation\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Class EpaFilterWarningText
 * @Filter(
 *   id = "epa_filter_warning_text",
 *   title = @Translation("Accessible warnings"),
 *   description = @Translation("Adds screen reader label to warning text."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 * )
 * @package Drupal\epa_wysiwyg\Plugin\Filter
 */
class EpaFilterWarningText extends FilterBase {
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
    $regex = '#(<\s*?span\b[^>]*class="[^"]*\bwarning\b[^"]*"[^>]*>)(.*?)(</span\b[^>]*>)#s';
    $replacement = '${1}<span class="u-visually-hidden">'
      . $this->t('Warning: ') . '</span>${2}${3}';
    $text = preg_replace($regex, $replacement, $text);
    return new FilterProcessResult($text);
  }
}
