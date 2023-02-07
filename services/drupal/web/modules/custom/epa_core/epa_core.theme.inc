<?php

/**
 * @file
 */

use Drupal\Component\Utility\Html;
use Drupal\Core\Link;

/**
 * Prepares term objects for Twig template.
 *
 * @param array $variables
 *   An associative array with preprocess variables for this theme.
 *   by theme_preprocess.
 */
function template_preprocess_hierarchical_term_facet_formatter(array &$variables) {
  $terms = [];
  foreach ($variables['terms'] as $term) {
    if ($variables['link'] && $variables['facet_source']) {
      $url = Drupal::service('facets.utility.url_generator')->getUrl([$variables['facet_source'] => [$term->id()]]);
      $link = Link::fromTextAndUrl($term->getName(), $url);
      $link = $link->toRenderable();
      $terms[] = render($link);
    }
    else {
      $terms[] = $term->getName();
    }
  }
  if ($variables['wrapper'] != 'none') {
    $count = 0;
    foreach ($terms as &$term) {
      $count++;
      $term = [
        '#type' => 'html_tag',
        '#tag' => (in_array($variables['wrapper'], ['ol', 'ul'])) ? 'li' : $variables['wrapper'],
        '#value' => $term,
        '#attributes' => [
          'class' => [
            Html::cleanCssIdentifier('taxonomy-term'),
            Html::cleanCssIdentifier("count $count"),
          ],
        ],
      ];
    }
  }

  unset($variables['link']);
  $variables['terms'] = $terms;
}
