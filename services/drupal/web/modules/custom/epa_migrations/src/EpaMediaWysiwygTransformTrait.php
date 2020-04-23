<?php

namespace Drupal\epa_migrations;

use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

/**
 * Helpers to transform embedded media tags.
 */
trait EpaMediaWysiwygTransformTrait {

  /**
   * Transform embedded media in wysiwyg content.
   *
   * @param string $wysiwyg_content
   *   The content to search and transform embedded media.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entityTypeManager service.
   */
  public function transformWysiwyg($wysiwyg_content, EntityTypeManager $entityTypeManager) {
    $view_modes = [
      'media_large' => 'large',
      'medium' => 'medium',
      'media_original' => 'original',
      'full' => 'original',
      'teaser' => 'small',
      'media_small' => 'small',
      'thumbnail' => 'small',
      'block_header' => 'small',
      'small' => 'small',
    ];

    $pattern = '/\[\[(?<tag_info>.+?"type":"media".+?)\]\]/s';

    $media_embed_replacement_template = <<<'TEMPLATE'
<drupal-media
  alt="%s"
  data-align="%s"
  data-caption="%s"
  data-entity-type="media"
  data-entity-uuid="%s"
  data-view-mode="%s"></drupal-media>
TEMPLATE;

    $inline_embed_replacement_template = <<<'TEMPLATE'
<drupal-inline-media
  data-align="center"
  data-entity-type="media"
  data-entity-uuid="%s"></drupal-media>
TEMPLATE;

    $wysiwyg_content = preg_replace_callback($pattern, function ($matches) use ($inline_embed_replacement_template, $media_embed_replacement_template, $entityTypeManager, $view_modes) {
      $decoder = new JsonDecode(TRUE);

      $tag_info = $decoder->decode($matches['tag_info'], JsonEncoder::FORMAT);
      $media_entity_uuid = $entityTypeManager->getStorage('media')
        ->load($tag_info['fid'])->uuid();

      if ($tag_info['view_mode'] === 'media_link') {
        return sprintf($inline_embed_replacement_template,
          $media_entity_uuid
        );
      }
      else {
        return sprintf($media_embed_replacement_template,
          $tag_info['fields']['field_file_image_alt_text[und][0][value]'] ?? '',
          $tag_info['fields']['field_image_alignment[und]'] ?? 'center',
          html_entity_decode($tag_info['fields']['field_caption[und][0][value]']) ?? '',
          $media_entity_uuid,
          $view_modes[$tag_info['view_mode']]
        );
      }
    }, $wysiwyg_content);

    return $wysiwyg_content;
  }

}
