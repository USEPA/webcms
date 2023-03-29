<?php

/**
 * @file
 * Field template functions.
 */

/**
 * Implements hook_theme_suggestions_field_alter().
 */
function epa_theme_theme_suggestions_field_alter(&$suggestions, $variables) {
  $suggestions[] = 'field__' . $variables['element']['#entity_type'] . '__' . $variables['element']['#field_name'] . '__' . $variables['element']['#bundle'] . '__' . $variables['element']['#view_mode'];
}

/**
 *
 */
function epa_theme_preprocess_field(&$variables) {
  $element = $variables['element'];
  if ($element['#bundle'] == 'perspective' && $element['#field_name'] == 'field_subjects' && $element['#view_mode'] == '_custom') {
    $node = $element['#object'];
    foreach ($node->entitygroupfield as $group) {
      array_unshift($variables['items'], [
        'content' => [
          '#type' => 'link',
          '#title' => 'Read more EPA Perspectives',
          '#url' => $group->entity->getGroup()->field_homepage->entity->toUrl(),
        ],
      ]);
    }
  }

  // Add index to individual paragraphs.
  if ($variables['field_type'] == 'entity_reference_revisions' && $variables['element']['#items']->getItemDefinition()->getSetting('target_type') == 'paragraph') {
    $delta = 0;
    foreach ($variables['items'] as $idx => $item) {
      if (!empty($variables['items'][$idx]['content']['#paragraph'])) {
        $variables['items'][$idx]['content']['#paragraph']->index = $delta;
        $delta++;
      }
    }
  }
}
