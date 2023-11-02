<?php

/**
 * @file
 * Media template functions.
 */

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\filter\Render\FilteredMarkup;

/**
 * Implements hook_preprocess_filter_caption().
 */
function epa_theme_preprocess_filter_caption(&$vars) {
  $dom = Html::load($vars['node']);
  foreach ($dom->getElementsByTagName('drupal-media') as $node) {
    /** @var \DOMElement */
    $new_node = $node->cloneNode(TRUE);

    if ($media = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties(['uuid' => $node->getAttribute('data-entity-uuid')])) {
      $media = reset($media);
      $vars['bundle'] = $media->bundle();
      // Map this to video since we have been using this term in our css everywhere already.
      if ($vars['bundle'] == 'remote_video') {
        $vars['bundle'] = 'video';
      }


      if ($media->hasField('field_limit_file_accessibility')) {
        /** @var \Drupal\access_unpublished\TokenGetter $tokenGetter */
        $tokenGetter = \Drupal::service('access_unpublished.token_getter');
        if ($token = $tokenGetter->getToken()) {
          if (_is_access_token_valid($token)) {
            $media->field_limit_file_accessibility->value = FALSE;
          }
        } else if ($referer_url = \Drupal::request()->headers->get('referer')) {
          // Individual file requests don't contain the hash key, so we have to
          // check the referer instead.
          $config = \Drupal::config('access_unpublished.settings');
          $referer_url_parsed = UrlHelper::parse($referer_url);
          if (isset($referer_url_parsed['query'][$config->get('hash_key')])) {
            $token = $referer_url_parsed['query'][$config->get('hash_key')];
            if (_is_access_token_valid($token)) {
              $media->field_limit_file_accessibility->value = FALSE;
            }
          }
        }

        $vars['media_accessibility'] = $media->field_limit_file_accessibility->value ? 'private' : 'public';
      }
    }

    // Re-insert the caption attribute into the media tag so that it is available
    // when rendering the media itself. This allows us to make decisions during
    // media rendering based upon whether or not there's a caption.
    $new_node->setAttribute('data-caption', $vars['caption']);
    $node->parentNode->replaceChild($new_node, $node);

    // Grab the view mode and alignment info from the embedded media tag and make
    // it available to the filter-caption template.
    foreach (['data-view-mode' => 'view_mode', 'data-align' => 'align'] as $attribute => $var) {
      $vars[$var] = $node->getAttribute($attribute);
    }
  }
  $vars['node'] = FilteredMarkup::create(Html::serialize($dom));
}

/**
 * Implements hook_preprocess_media().
 */
function epa_theme_preprocess_media(&$vars) {
  $vars['has_caption'] = isset($vars['attributes']['data-caption']);
  /** @var \Drupal\media\Entity\Media $media */
  $media = $vars['media'];
  $vars['filesize'] = format_size($media->getSource()->getMetadata($media, 'filesize'), NULL, 1);
  if ($media->hasField('field_limit_file_accessibility')) {
    $vars['media_accessibility'] = $media->field_limit_file_accessibility->value ? 'private' : 'public';
  }
}

/**
 * Implements hook_preprocess_responsive_image().
 */
function epa_theme_preprocess_responsive_image(&$vars) {
  // Add class to box images.
  if ($vars['responsive_image_style_id'] == 'responsive_box_image') {
    $vars['img_element']['#attributes']['class'][] = 'box__image';
  }

  // Load file by filename
  // array.
  if ($file = \Drupal::entityTypeManager()
    ->getStorage('file')
    ->loadByProperties(['uri' => $vars['uri']])) {
    $fid = array_shift($file)->fid->value;

    if ($media = \Drupal::entityTypeManager()
      ->getStorage('media')
      ->loadByProperties(['field_media_image' => $fid])) {
      $media = array_shift($media);
      if ($media->field_banner_image_restricted->value == 'restricted') {
        $vars['img_element']['#attributes']['class'][] = 'restricted-img';
      }
    }
  }
}

/**
 *
 */
function epa_theme_preprocess_image(&$vars) {
  if (empty($vars['attributes']['alt'])) {
    $vars['attributes']['alt'] = '';
  }
}
