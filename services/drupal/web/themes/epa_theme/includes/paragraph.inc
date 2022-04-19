<?php

/**
 * @file
 * Paragraph template functions.
 */

use Drupal\Core\Render\Element;

/**
 * Implements theme_preprocess_paragraph()
 */
function epa_theme_preprocess_paragraph__slideshow(&$variables) {
  $slides = $variables['content']['field_slides'];

  foreach (Element::children($slides) as $key) {
    $variables['slides'][$key]['caption'] = $slides[$key]['#paragraph']->field_caption;
    $variables['slides'][$key]['media'] = $slides[$key]['#paragraph']->field_slide_image;
  }
}

/**
 * Implements theme_preprocess_paragraph()
 */
function epa_theme_preprocess_paragraph__card(&$variables) {
  $card = $variables['paragraph'];
  $parent = $card->_referringItem->getEntity();
  $card_style = $parent->field_card_style;
  if (!$card_style->isEmpty()) {
    $style = $card_style->getValue()[0]['value'];
  } else {
    $style = false;
  }
  // If flag is set, pass to card template
  $variables['flag'] = $style == 'flag';
}
