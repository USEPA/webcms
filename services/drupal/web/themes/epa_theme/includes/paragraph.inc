<?php

/**
 * @file
 * Paragraph template functions.
 */

use Drupal\Core\Render\Element;

/**
 * Implements theme_preprocess_paragraph().
 */
function epa_theme_preprocess_paragraph(&$variables) {
  if (isset($variables['paragraph']->parent_field_name)) {
    $variables['parent_field'] = $variables['paragraph']->parent_field_name->value;
  }

  if ($variables['paragraph']->getParentEntity()) {
    $variables['parent_type'] = $variables['paragraph']->getParentEntity()->getEntityTypeId();
    $variables['parent_bundle'] = $variables['paragraph']->getParentEntity()->bundle();
  }
}

/**
 * Implements theme_preprocess_paragraph()
 */
function epa_theme_preprocess_paragraph__slideshow(&$variables) {
  $slides = $variables['content']['field_slides'];
  foreach (Element::children($slides) as $key) {
    // If media item is private and user is anonymous we should remove it from display.
    /** @var \Drupal\media\MediaInterface $media_item */
    $media_item = $slides[$key]['#paragraph']->field_slide_image->entity;
    if (\Drupal::currentUser()->isAnonymous() &&
      $media_item->hasField('field_limit_file_accessibility')
    ) {
      if ($media_item->get('field_limit_file_accessibility')->value == 1) {
        continue;
      }
    }
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
  /** @var \Drupal\Core\Entity\ContentEntityBase $parent */
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

  // If the 'flag layout' field is set, add modifier classes to the card.
  if ($style == 'flag') {
    if ($card->get('field_flag_card_alignment')->isEmpty() || $card->get('field_flag_card_alignment')->value == 'default') {
      // Add left/right classes based on paragraph index if alignment isn’t
      // already set.
      if($card->index % 2 == 0) {
        if (!isset($variables['modifier_classes'])) {
          $variables['modifier_classes'] = 'usa-card--media-left';
        }
        else {
          $variables['modifier_classes'] .= ' usa-card--media-left';
        }
      }
      else{
        if (!isset($variables['modifier_classes'])) {
          $variables['modifier_classes'] = 'usa-card--media-right';
        }
        else {
          $variables['modifier_classes'] .= ' usa-card--media-right';
        }
      }
    }
    else {
      if ($card->get('field_flag_card_alignment')->value == 'right') {
        if (!isset($variables['modifier_classes'])) {
          $variables['modifier_classes'] = 'usa-card--media-right';
        }
        else {
          $variables['modifier_classes'] .= ' usa-card--media-right';
        }
      }
      elseif($card->get('field_flag_card_alignment')->value == 'left') {
        if (!isset($variables['modifier_classes'])) {
          $variables['modifier_classes'] = 'usa-card--media-left';
        }
        else {
          $variables['modifier_classes'] .= ' usa-card--media-left';
        }
      }
    }
  }

  if ($parent->hasField('field_title_placement')) {
    $variables['title_placement'] = $parent->get('field_title_placement')->value;
  }

  if ($parent->hasField('field_image_style')) {
    $variables['image_placement_style'] = $parent->get('field_image_style')->value;
  }
}

/**
 * Implements template_preprocess_paragraph()
 */
function epa_theme_preprocess_paragraph__featured_links(&$variables) {
  $paragraph = $variables['paragraph'];

  // Get parent component.
  $parent_uuid = $paragraph->getBehaviorSetting('layout_paragraphs', 'parent_uuid');

  // If parent component is set, load it and get behavior settings.
  if (!empty($parent_uuid)) {
    $parent_component = \Drupal::service('entity.repository')->loadEntityByUuid('paragraph', $parent_uuid);

    // If parent component has a color class, pass it to the paragraph template.
    $parent_class_color = $parent_component->getBehaviorSetting('layout_paragraphs', ['config', 'classes', 'color']);
    if (!empty($parent_class_color)) {
      $variables['parent_class_color'] = $parent_class_color;
    }
  }
}
