<?php

use Drupal\Core\Link;

/**
 * Implements hook_preprocess_node__BUNDLE__VIEW_MODE().
 */
function epa_theme_preprocess_node__news_release__full(&$variables) {
  /** @var \Drupal\node\NodeInterface $node */
  $node = $variables['node'];

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
          'Lisez les communiqués de presse de l\'EPA en français',
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

    if ($link instanceof Link) {
      $variables['more_link'] = $link->toRenderable();
    }
  }
}
