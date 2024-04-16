<?php

namespace Drupal\Tests\content_moderation_notifications\Kernel;

use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\workflows\Entity\Workflow;

/**
 * Helper functions for testing workflow participants.
 */
trait ContentModerationNotificationTestTrait {

  use ContentModerationTestTrait;

  /**
   * Creates a page node type to test with, ensuring that it's moderated.
   *
   * @param string $entity_type
   *   The entity type ID to enable workflow for.
   * @param string $bundle
   *   The bundle ID.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\workflows\Entity\Workflow
   *   The 'editorial' workflow.
   */
  protected function enableModeration($entity_type = 'entity_test_rev', $bundle = 'entity_test_rev') {
    // Check if workflow has already been created.
    if (!$workflow = Workflow::load('editorial')) {
      $workflow = $this->createEditorialWorkflow();
    }
    $workflow->getTypePlugin()->addEntityTypeAndBundle($entity_type, $bundle);
    $workflow->save();
    return $workflow;
  }

}
