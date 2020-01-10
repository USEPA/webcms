<?php

namespace Drupal\epa_workflow;

use Drupal\content_moderation\Entity\ContentModerationStateInterface;

/**
 * Processes approved draft content.
 */
class EPADraftApproved extends EPAModeration {

  /**
   * {@inheritdoc}
   */
  protected $moderationName = 'draft_approved';

  /**
   * {@inheritdoc}
   */
  public function process(ContentModerationStateInterface $moderation_entity) {
    parent::process($moderation_entity);

    $this->clearScheduledTransitions();

    $this->scheduleTransition('field_publish_date', 'published');
  }

  /**
   * Sets other draft approved revisions to draft.
   *
   * Although this does not affect what is and isn't scheduled,
   * we at minimum need this trigger a notification of some sort.
   *
   * @todo Might have to send this to a batch process.
   */
  protected function unapproveOtherDrafts() {
    $entity_id = $this->contentEntityRevision->id();
    $current_revision_id = $this->contentEntityRevision->getLoadedRevisionId();
    $entity_type = $this->contentEntityRevision->getEntityTypeId();
    $storage = $this->entityTypeManager()->getStorage($entity_type);
    $query = $storage->getQuery();
    $results = $query->allRevisions()
      ->condition('entity_id', $entity_id)
      ->condition('field_scheduled_transition_moderation_state', 'draft_approved')
      ->execute();
    if (!empty($results)) {
      $revision_ids = array_keys($results);
      unset($revision_ids[$current_revision_id]);
      foreach ($revision_ids as $revision_id) {
        $revision = $storage->loadRevision($revision_id);
        $revision->setSyncing(TRUE);
        $revision->setNewRevision(FALSE);
        $revision->set('moderation_state', 'draft');
        $revision->save();
        $this->logger->notice('Moving to draft state due to approval of revision %:vid.', ['%vid' => $revision_id]);
      }
    }
  }

}
