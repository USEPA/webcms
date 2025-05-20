<?php

namespace Drupal\media_inline_embed\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\filter\FilterProcessResult;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\media\MediaInterface;
use Drupal\media\Plugin\Filter\MediaEmbed;

/**
 * Extends Drupal's MediaEmbed class.
 *
 * @Filter(
 *   id = "media_inline_embed",
 *   title = @Translation("Embed media inline"),
 *   description = @Translation("Embeds media items using a custom tag, <code>&lt;drupal-inline-media&gt;</code>. If used in conjunction with the 'Align/Caption' filters, make sure this filter is configured to run after them."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 *   settings = {
 *     "default_view_mode" = "default",
 *     "allowed_view_modes" = {},
 *     "allowed_media_types" = {},
 *   },
 *   weight = 100,
 * )
 */
class MediaInlineEmbed extends MediaEmbed {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    if (stristr($text, '<drupal-inline-media') === FALSE) {
      return $result;
    }

    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);

    foreach ($xpath->query('//*[@data-entity-type="media" and normalize-space(@data-entity-uuid)!=""]') as $node) {
      /** @var \DOMElement $node */
      $uuid = $node->getAttribute('data-entity-uuid');
      $view_mode_id = $node->getAttribute('data-view-mode') ?: $this->settings['default_view_mode'];

      // Delete the consumed attributes.
      $node->removeAttribute('data-entity-type');
      $node->removeAttribute('data-entity-uuid');
      $node->removeAttribute('data-view-mode');

      $media = $this->entityRepository->loadEntityByUuid('media', $uuid);
      assert($media === NULL || $media instanceof MediaInterface);
      if (!$media) {
        $this->loggerFactory->get('media')->error('During rendering of embedded media: the media item with UUID "@uuid" does not exist.', ['@uuid' => $uuid]);
      }
      else {
        $media = $this->entityRepository->getTranslationFromContext($media, $langcode);
        $media = clone $media;
        $this->applyPerEmbedMediaOverrides($node, $media);
      }

      $view_mode = NULL;
      if ($view_mode_id !== EntityDisplayRepositoryInterface::DEFAULT_DISPLAY_MODE) {
        $view_mode = $this->entityRepository->loadEntityByConfigTarget('entity_view_mode', "media.$view_mode_id");
        if (!$view_mode) {
          $this->loggerFactory->get('media')->error('During rendering of embedded media: the view mode "@view-mode-id" does not exist.', ['@view-mode-id' => $view_mode_id]);
        }
      }

      $build = $media && ($view_mode || $view_mode_id === EntityDisplayRepositoryInterface::DEFAULT_DISPLAY_MODE)
        ? $this->renderMedia($media, $view_mode_id, $langcode)
        : $this->renderMissingMediaIndicator();

      if (empty($build['#attributes']['class'])) {
        $build['#attributes']['class'] = [];
      }
      // Any attributes not consumed by the filter should be carried over to the
      // rendered embedded entity. For example, `data-align` and `data-caption`
      // should be carried over, so that even when embedded media goes missing,
      // at least the caption and visual structure won't get lost.
      foreach ($node->attributes as $attribute) {
        if ($attribute->nodeName == 'class') {
          // We don't want to overwrite the existing CSS class of the embedded
          // media (or if the media entity can't be loaded, the missing media
          // indicator). But, we need to merge in CSS classes added by other
          // filters, such as filter_align, in order for those filters to work
          // properly.
          $build['#attributes']['class'] = array_unique(array_merge($build['#attributes']['class'], explode(' ', $attribute->nodeValue)));
        }
        else {
          $build['#attributes'][$attribute->nodeName] = $attribute->nodeValue;
        }
      }

      $this->renderIntoDomNode($build, $node, $result);
    }

    $result->setProcessedText(Html::serialize($dom));

    return $result;
  }

  /**
   * Builds the render array for the indicator when media cannot be loaded.
   *
   * @return array
   *   A render array.
   */
  protected function renderMissingMediaIndicator() {
    $output = parent::renderMissingMediaIndicator();
    $output['#inline'] = TRUE;
    return $output;
  }

}
