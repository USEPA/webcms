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
