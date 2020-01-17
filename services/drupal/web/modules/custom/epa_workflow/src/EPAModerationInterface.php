<?php

namespace Drupal\epa_workflow;

use Drupal\content_moderation\Entity\ContentModerationStateInterface;

/**
 * Provides interface for moderation process.
 */
interface EPAModerationInterface {

  /**
   * Process given moderation state entity.
   *
   * @param Drupal\content_moderation\Entity\ContentModerationStateInterface $moderation_entity
   *   The moderation state.
   */
  public function process(ContentModerationStateInterface $moderation_entity);

}
