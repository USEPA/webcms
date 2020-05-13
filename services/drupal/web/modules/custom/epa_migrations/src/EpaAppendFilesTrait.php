<?php

namespace Drupal\epa_migrations;

use Drupal\Core\Entity\EntityTypeManager;

/**
 * Helpers to append inline media embeds to content, given a list of file ids.
 */
trait EpaAppendFilesTrait {

  /**
   * Append files as an unordered list of inline media.
   *
   * @param string $value
   *   The string to append the media to.
   * @param array $fids
   *   The list of file ids to transform into media embeds.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   *
   * @return string
   *   The input value with the media appended.
   */
  public function appendFiles(string $value, array $fids, EntityTypeManager $entityTypeManager) {
    $unordered_list = '<ul>';

    foreach ($fids as $fid) {
      $unordered_list .= '<li>' . $this->createMediaInlineEmbed($fid['fid'], $entityTypeManager) . '</li>';
    }

    $unordered_list .= '</ul>';

    // Append the unordered list to the incoming value.
    $value = $value . $unordered_list;

    return $value;

  }

  /**
   * Given an fid, return an inline media embed tag.
   *
   * @param int $fid
   *   The file id.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   *
   * @return string
   *   The formatted inline media embed tag.
   */
  private function createMediaInlineEmbed(int $fid, EntityTypeManager $entityTypeManager) {

    $inline_embed_replacement_template = <<<'TEMPLATE'
<drupal-inline-media
  data-align="center"
  data-entity-type="media"
  data-entity-uuid="%s"></drupal-inline-media>
TEMPLATE;

    $media_entity_uuid = $entityTypeManager->getStorage('media')
      ->load($fid);

    $media_entity_uuid = $media_entity_uuid ? $media_entity_uuid->uuid() : 0;

    return sprintf($inline_embed_replacement_template,
      $media_entity_uuid
    );
  }

}
