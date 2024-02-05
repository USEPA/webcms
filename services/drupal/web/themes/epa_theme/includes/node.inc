<?php

/**
 * @file
 */

use Drupal\Core\Link;
use Drupal\group\Entity\GroupContent;

/**
 * Implements hook_preprocess_node().
 */
function epa_theme_preprocess_node(&$variables) {
  $view_mode = $variables['view_mode'];

  // Array of view modes to add regions to.
  $allowed_view_modes = ['full'];

  if (in_array($view_mode, $allowed_view_modes)) {
    // Array of regions to add to node template.
    $allowed_regions = ['messages'];
    _add_regions_to_node($allowed_regions, $variables);
    $node = $variables['node'];
    _add_webarea_contact_link($node, $variables);
  }
}

/**
 * Implements hook_preprocess_node__VIEW_MODE().
 *
 * Retrieves the node's Group's referenced webform and adds it as a variable.
 */
function epa_theme_preprocess_node__full(&$variables) {
  /** @var \Drupal\node\Entity\Node $node */
  $node = $variables['node'];
  if (!isset($variables['webarea_contact_link'])) {
    _add_webarea_contact_link($node, $variables);
  }
}

/**
 * Implements hook_preprocess_node__BUNDLE__VIEW_MODE().
 */
function epa_theme_preprocess_node__news_release__full(&$variables) {
  /** @var \Drupal\node\NodeInterface $node */
  $node = $variables['node'];
  _add_webarea_contact_link($node, $variables);

  $language = $node->get('field_language')->value;

  if ($language !== 'en') {
    $language_url = Drupal::service('facets.utility.url_generator')
      ->getUrl(['news_releases_language' => [$language]]);
    switch ($language) {
      case 'es':
        $link = Link::fromTextAndUrl(
          'Lea los comunicados de prensa de la EPA en español',
          $language_url
        );
        break;

      case 'ar':
        $link = Link::fromTextAndUrl(
          'اقرأ النشرات الإخباریة لوكالة حمایة البیئة باللغة العربیة',
          $language_url
        );
        break;

      case 'zh_CN':
        $link = Link::fromTextAndUrl(
          '阅读环保署新闻稿简体中文版',
          $language_url
        );
        break;

      case 'zh_TW':
        $link = Link::fromTextAndUrl(
          '閱讀環保署新聞稿繁體中文版',
          $language_url
        );
        break;

      case 'fr':
        $link = Link::fromTextAndUrl(
          'Lire les communiqués de presse de l’EPA en français',
          $language_url
        );
        break;

      case 'de':
        $link = Link::fromTextAndUrl(
          'Lesen Sie EPA-Pressemitteilungen auf Deutsch',
          $language_url
        );
        break;

      case 'bn':
        $link = Link::fromTextAndUrl(
          'বাংলায়> প্রকাশিত ইপিএ খবর পড়ুন',
          $language_url
        );
        break;

      case 'gu':
        $link = Link::fromTextAndUrl(
          'EPA ની સમાચાર જાહેરાત ગુજરાતી માં વાંચો',
          $language_url
        );
        break;

      case 'ht':
        $link = Link::fromTextAndUrl(
          'Li kominike laprès EPA yo an Kreyòl Ayisyen',
          $language_url
        );
        break;

      case 'it':
        $link = Link::fromTextAndUrl(
          'Leggi i comunicati stampa EPA in italiano',
          $language_url
        );
        break;

      case 'ko':
        $link = Link::fromTextAndUrl(
          '한국어로 된 EPA 뉴스 보도자료를 읽어보십시오',
          $language_url
        );
        break;

      case 'pt':
        $link = Link::fromTextAndUrl(
          'Leia estes boletins informativos da EPA em Português',
          $language_url
        );
        break;

      case 'ru':
        $link = Link::fromTextAndUrl(
          'Читайте информационные выпуски EPA на русском',
          $language_url
        );
        break;

      case 'tl':
        $link = Link::fromTextAndUrl(
          'Basahin ang mga ipinalabas na balita ng EPA sa Tagalog',
          $language_url
        );
        break;

      case 'vi':
        $link = Link::fromTextAndUrl(
          'Đọc bản tin EPA mới phát hành bằng tiếng Việt',
          $language_url
        );
        break;

      default;
    }

    if (isset($link) && $link instanceof Link) {
      $variables['more_link'] = $link->toRenderable();
    }
  }
}

/**
 * Custom function to add page regions to node templates.
 */
function _add_regions_to_node($allowed_regions, &$variables) {
  // Get active theme.
  $theme = \Drupal::theme()->getActiveTheme()->getName();

  // Get current theme regions.
  $available_regions = system_region_list($theme, 'REGIONS_ALL');

  // Validate allowed regions with available regions.
  $regions = array_intersect(array_keys($available_regions), $allowed_regions);

  foreach ($regions as $key => $region) {
    // Load blocks from this region and sort them.
    $blocks = \Drupal::entityTypeManager()
      ->getStorage('block')
      ->loadByProperties(['theme' => $theme, 'region' => $region]);
    uasort($blocks, 'Drupal\block\Entity\Block::sort');

    // Build blocks and assign to template variable.
    $build = [];
    $builder = \Drupal::entityTypeManager()->getViewBuilder('block');
    foreach ($blocks as $key => $block) {
      if ($block->access('view')) {
        $build[$key] = $builder->view($block, 'block');
      }
    }
    $variables['region_' . $region] = $build;
  }
}

/**
 * Custom function to add Web Area contact link to node templates.
 *
 * @param $entity
 *   The node to get the Group contact link for.
 * @param $variables
 *   The node render array variables.
 *
 * @return void
 * @throws \Drupal\Core\Entity\EntityMalformedException
 */
function _add_webarea_contact_link($entity, &$variables) {
  $group_contents = GroupContent::loadByEntity($entity);
  if (!empty($group_contents)) {
    /** @var \Drupal\group\Entity\GroupContent $group_content */
    $group_content = reset($group_contents);
    $group = $group_content->getGroup();
    /** @var \Drupal\node\Entity\Node[] $webform */
    $webform = $group->get('field_contact_us_form')->referencedEntities();
    $webform = reset($webform);
    // Only apply the variable if the link isn't to itself.
    if ($webform && ($entity->id() !== $webform->id())) {
      $variables['webarea_contact_link'] = $webform->toLink()->toRenderable();
    }
  }
}
