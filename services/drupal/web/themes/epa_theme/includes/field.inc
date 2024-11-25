<?php

/**
 * @file
 * Field template functions.
 */

use Drupal\Core\Render\Element;
use Drupal\node\NodeInterface;

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

  if ($element['#field_name'] == 'field_subjects') {
    _alter_field_subjects($variables);
  }
}

/**
 * Custom alteration logic for field_subjects to output additional text.
 *
 * @param $variables
 *   The $variables render array from epa_theme_preprocess_field.
 *
 * @return void
 *
 * Custom function to add some additional text to the field_subjects links.
 * The items are themed as `hierarchical_term_facet_formatter` which is a custom
 * formatter in the epa_core module. The link text is typically derived from the
 * referenced Term's name. Here we alter the name to get the additional text we want
 *
 * @see \template_preprocess_hierarchical_term_facet_formatter()
 * @link https://forumone.atlassian.net/browse/EPAD8-2406
 */
function _alter_field_subjects(&$variables) {
  // Prevent moving on if not a node. This custom function only applies to
  // nodes that have the field_subjects
  if (!$variables['element']['#object'] instanceof NodeInterface) {
    return;
  }

  /** @var \Drupal\node\Entity\Node $node */
  $node = $variables['element']['#object'];

  // If field subjects is empty exit out.
  if ($node->get('field_subjects')->isEmpty()) {
    return;
  }

  // We want to display the bundle label, however those are singular. There's
  // currently no way in core to provide a "plural" label so we'll provide our own.
  // @todo Review this later when https://www.drupal.org/node/2773615 lands.
  $plural_bundles = [
    'perspective' => 'Perspectives',
    'news_release' => 'News Releases',
    'speeches' => 'Speeches and Remarks',
  ];

  // Loop over each subject in the field and alter the term name to be what we want.
  foreach (Element::children($variables['items']) as $key) {
    // See logic above in \epa_theme_preprocess_field.
    // We do some alterations for this field already if it's a perspective node.
    if (!isset($variables['items'][$key]['content']['#terms'])) {
      continue;
    }

    /** @var \Drupal\taxonomy\Entity\Term[] $terms */
    $terms = $variables['items'][$key]['content']['#terms'];

    $term = reset($terms);
    $translated = t("Read other EPA @bundle about @name", [
      '@bundle' => $plural_bundles[$node->bundle()],
      '@name' => $term->getName(),
    ]);
    $term->setName($translated);
    $variables['items'][$key]['content']['#terms'][0] = $term;
  }

}
