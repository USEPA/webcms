<?php

/**
 * @file
 * Paragraph template functions.
 */

use Drupal\Core\Render\Element;
use Drupal\Core\Template\Attribute;

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
  /** @var \Drupal\paragraphs\Entity\Paragraph $card */
  $card = $variables['paragraph'];
  /** @var \Drupal\Core\Entity\ContentEntityInterface $parent */
  $parent = $card->_referringItem->getEntity();
  $card_style = $parent->field_card_style;
  if (!$card_style->isEmpty()) {
    $style = $card_style->getValue()[0]['value'];
  }
  else {
    $style = FALSE;
  }
  // If flag is set, pass to card template.
  $variables['flag'] = $style == 'flag';

  // If the 'flag layout' field is set to right and we've chosen to display as
  // the flag type, add modifier class to the card.
  if (!$card->get('field_flag_card_alignment')->isEmpty() &&
    $card->get('field_flag_card_alignment')->value == 'right' &&
    $style == 'flag' ) {
    if (!isset($variables['modifier_classes'])) {
      $variables['modifier_classes'] = 'usa-card--media-right';
    }
    else {
      $variables['modifier_classes'] .= ' usa-card--media-right';
    }
  }

  if (!$card->get('field_flag_card_alignment')->isEmpty() &&
    $card->get('field_flag_card_alignment')->value == 'default' &&
    $style == 'flag' ) {
    if (!isset($variables['modifier_classes'])) {
      $variables['modifier_classes'] = 'usa-card--media-left';
    }
    else {
      $variables['modifier_classes'] .= ' usa-card--media-left';
    }
  }
}
