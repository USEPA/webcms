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
   * @param bool $remove_alignment
   *   A flag to determine whether the alignment setting should be set to null.
   *
   * @return string
   *   The original wysiwyg_content with embedded media in D8 format.
   */
  public function transformWysiwyg($wysiwyg_content, EntityTypeManager $entityTypeManager, $remove_alignment = FALSE) {
    $view_modes = [
      'media_large' => 'large',
      'medium' => 'medium',
      'media_original' => 'original',
      'full' => 'original',
      'teaser' => 'small',
      'media_small' => 'small',
      'thumbnail' => 'thumbnail',
      'block_header' => 'small',
      'small' => 'small',
    ];

    $pattern = '/\[\[(?<tag_info>.+?"type":"media".+?)\]\]/s';

    $inline_embed_replacement_template = <<<'TEMPLATE'
<drupal-inline-media
  data-entity-type="media"
  data-view-mode="link_with_metadata"
  data-entity-uuid="%s"></drupal-inline-media>
TEMPLATE;

    // We migrate documents with the description displayed by default.
    $inline_embed_pdf_replacement_template = <<<'TEMPLATE'
<drupal-inline-media
  data-entity-type="media"
  data-view-mode="link_with_description"
  data-entity-uuid="%s"></drupal-inline-media>
TEMPLATE;

    // Fix these malformed JSON strings
    $wysiwyg_content = str_replace('"alt":"\\\\\\"""', '"alt":""', $wysiwyg_content);

    $wysiwyg_content = preg_replace_callback($pattern, function ($matches) use ($inline_embed_replacement_template, $inline_embed_pdf_replacement_template, $entityTypeManager, $view_modes, $remove_alignment) {
      $decoder = new JsonDecode(TRUE);

      try {
        $tag_info = $decoder->decode($matches['tag_info'], JsonEncoder::FORMAT);

        $media_entity = $entityTypeManager->getStorage('media')
          ->load($tag_info['fid']);
        $media_entity_uuid = $media_entity ? $media_entity->uuid() : 0;

        // Return an inline media embed.
        if ($tag_info['view_mode'] === 'media_link') {
          if ($media_entity->bundle() === 'document') {
            return sprintf($inline_embed_pdf_replacement_template,
              $media_entity_uuid
            );
          }
          else {
            return sprintf($inline_embed_replacement_template,
              $media_entity_uuid
            );
          }
        }
        // Return a full media embed.
        else {
          $doc = new \DOMDocument();
          $el = $doc->createElement('drupal-media');
          $el->setAttribute('data-entity-type', 'media');
          $el->setAttribute('data-entity-uuid', $media_entity_uuid);
          $el->setAttribute('data-view-mode', $view_modes[$tag_info['view_mode']]);

          $alignment = $remove_alignment ? '' : $tag_info['fields']['field_image_alignment[und]'] ?? 'center';
          $el->setAttribute('data-align', $alignment);

          $caption = stripslashes(urldecode($tag_info['fields']['field_caption[und][0][value]'] ?? ''));
          if (!empty($caption)) {
            $el->setAttribute('data-caption', $caption);
          }

          $alt = $tag_info['fields']['field_file_image_alt_text[und][0][value]'] ?? '';
          if (!empty($alt)) {
            $el->setAttribute('alt', $alt);
          }

          // If the 'link to original' setting is selected in D7, wrap the
          // <drupal-media> element in a link to the original image.
          $link_to_original = $tag_info['fields']['field_original_image_link[und]'] ?? '';
          if (!empty($link_to_original) && $link_to_original == 1 && $media_entity && $media_entity->bundle->entity->label() == 'Image') {
            $original_image_url = $media_entity->field_media_image->entity->getFileUri();
            if (str_starts_with($original_image_url, 'public://')) {
              // Since we have to hard-code a link here, and we are inconsistent
              // in various environments as to what domain files are hosted on,
              // we need to reference /sites/default/files/* which
              // EpaMediaS3fsSubscriber will then ensure will get redirected to
              // the right location if the file doesn't actually exist there.
              $original_image_url = substr_replace($original_image_url, '/sites/default/files/', 0, 9);
              $link_element = $doc->createElement('a');
              $link_element->setAttribute('href', $original_image_url);
              $link_element->appendChild($el);
              $doc->appendChild($link_element);
            }
            else {
              $doc->appendChild($el);
            }
          }
          else {
            $doc->appendChild($el);
          }

          return $doc->saveHTML();
        }
      }
      catch (\Exception $e) {
        \Drupal::logger('epa_migrations')->notice('Caught exception: ' . $e->getMessage() . ' while trying to process this json: ' . $matches['tag_info']);
      }
    }, $wysiwyg_content);

    return $wysiwyg_content;
  }

  /**
   * Extract block_header media from wysiwyg content.
   *
   * @param string $wysiwyg_content
   *   The content to search and extract block_header media.
   *
   * @return array
   *   An array that consists of the extracted block_header and the original
   *   wysiwyg_content with the block header removed.
   */
  public function extractBlockHeader($wysiwyg_content) {
    // Fix these malformed JSON strings
    $wysiwyg_content = str_replace('"alt":"\\\\\\"""', '"alt":""', $wysiwyg_content);

    $pattern = '~\[\[(.+?"type":"media".+?)\]\]~s';
    $split = preg_split($pattern, $wysiwyg_content, 2, PREG_SPLIT_DELIM_CAPTURE);
    /**
     * $split is:
     *   [0 => before string, 1 => captured JSON, 2 => after string]
     * OR:
     *   [0 => full string]
     * OR:
     *   false
     */

    if ($split && count($split) === 3) {
      list( $before, $captured, $after) = $split;
      if (strpos($captured, '}]]') !== false) {
        // Well, this is embarrassing. The pattern captured past the first media block's
        // closing }]] and matched "type":"media" in a second block. We're just going to
        // bail because the case we care about starts with a block_header first.
        // TODO split by the pattern ~(\[\[{|}\]\])~ and use a state machine to process.
        return [
          'block_header_url' => NULL,
          'block_header_img' => NULL,
          'wysiwyg_content' => $wysiwyg_content,
        ];
      }

      try {
        $decoder = new JsonDecode(TRUE);
        $tag_info = $decoder->decode($captured, JsonEncoder::FORMAT);
        $view_mode = $tag_info['view_mode'] ?? '';
        $width = $tag_info['attributes']['width'] ?? '';
        $height = $tag_info['attributes']['height'] ?? '';
        if ($view_mode == 'block_header' || ($width == 325 && $height == 100)) {
          $block_header = [
            'target_id' => $tag_info['fid'],
            'alt' => $tag_info['attributes']['alt'],
          ];

          // If an anchor starts in $before and ends in $after, we
          // remove the link and capture its href. Allow only whitespace
          // other than that media object. These patterns aren't bulletproof
          // but if they fail, it just leaves an empty link.
          $p1 = '~(.*)<a [^>]*\bhref="([^"]+)"[^>]*>\s*~s';
          $p2 = '~\s*</a>(.*)~s';

          $url = NULL;
          if (preg_match($p1, $before, $m1) && preg_match($p2, $after, $m2)) {
            list (, $before, $url) = $m1;
            list (, $after) = $m2;
          }

          if ($url !== NULL) {
            $url = $this->normalizeUrl($url);
          }

          // Let's try to remove the surrounding figure div as well
          $p1 = '~(.*)<div [^>]*\bclass="([^"]+)"[^>]*>\s*~s';
          $class_pattern = '~\bfigure\b~';
          $p2 = '~\s*</div>(.*)~s';
          if (preg_match($p1, $before, $m1)
            && preg_match($class_pattern, $m1[2])
            && preg_match($p2, $after, $m2)
          ) {
            list (, $before) = $m1;
            list (, $after) = $m2;
          }

          return [
            'block_header_url' => $url,
            'block_header_img' => $block_header,
            'wysiwyg_content' => $before . $after,
          ];
        }

      }
      catch (\Exception $e) {
        \Drupal::logger('epa_migrations')->notice('Caught exception: ' . $e->getMessage() . ' while trying to process this json: ' . $captured);
      }
    }

    return [
      'block_header_url' => NULL,
      'block_header_img' => NULL,
      'wysiwyg_content' => $wysiwyg_content,
    ];
  }

  protected function normalizeUrl($url) {
    if (preg_match('~^https?:~', $url)) {
      return $url;
    }

    if (preg_match('~^//([^/]+)/(.*)~', $url, $matches)) {
      // Protocol-relative, uncommon but valid
      list (, $domain, $path) = $matches;
      return "https://$domain/$path";
    }

    // Assume internal. This will turn relative paths to absolute,
    // but there's nothing we can really do about that.
    return "internal:/" . ltrim($url, '/');
  }

}
