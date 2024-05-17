<?php

namespace Drupal\danse_moderation_notifications\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of danse_moderation_notifications entities.
 *
 * List Controllers provide a list of entities in a tabular form. The base
 * class provides most of the rendering logic for us. The key functions
 * we need to override are buildHeader() and buildRow(). These control what
 * columns are displayed in the table, and how each row is displayed
 * respectively.
 *
 * Drupal locates the list controller by looking for the "list" entry under
 * "controllers" in our entity type's annotation. We define the path on which
 * the list may be accessed in our module's *.routing.yml file. The key entry
 * to look for is "_entity_list". In *.routing.yml, "_entity_list" specifies
 * an entity type ID. When a user navigates to the URL for that router item,
 * Drupal loads the annotation for that entity type. It looks for the "list"
 * entry under "controllers" for the class to load.
 *
 * @package Drupal\danse_moderation_notifications\Controller
 *
 * @ingroup danse_moderation_notifications
 */
class DanseModerationNotificationsListBuilder extends ConfigEntityListBuilder {

  /**
   * Builds the header row for the entity listing.
   *
   * @return array
   *   A render array structure of header strings.
   *
   * @see Drupal\Core\Entity\EntityListController::render()
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['workflow'] = $this->t('Workflow');
    $header['status'] = $this->t('Status');
    $header['transition'] = $this->t('Transitions');
    $header['roles'] = $this->t('Email Roles');
    $header['author'] = $this->t('Original Author');
    $header['revision_author'] = $this->t('Revision Author');
    $header['emails'] = $this->t('Adhoc Emails');
    return $header + parent::buildHeader();
  }

  /**
   * Builds a row for an entity in the entity listing.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to build the row.
   *
   * @return array
   *   A render array of the table row for displaying the entity.
   *
   * @see Drupal\Core\Entity\EntityListController::render()
   */
  public function buildRow(EntityInterface $entity) {

    // Load the workflow @todo change to dependency injection.
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = \Drupal::entityTypeManager()->getStorage('workflow')->load($entity->workflow);

    // Load the transitions in this workflow.
    $workflow_transitions = $workflow->getTypePlugin()->getTransitions();

    $row = [];

    // Array of transitions used in each row.
    $transition_strings = [];

    // Loop through the saved transitions.
    if ($entity->transitions) {
      $transitions = array_keys(array_filter($entity->transitions));
    }
    foreach ($transitions as $transition) {
      if (!empty($workflow_transitions[$transition])) {
        $transition_strings[] = $workflow_transitions[$transition]->label();
      }
    }

    $row['label'] = $entity->label();
    $row['workflow'] = $workflow->label();
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    $row['transition'] = implode(', ', $transition_strings);

    $roles = [];
    if ($entity->roles) {
      $roles = array_keys(array_filter($entity->roles));
    }

    $row['roles'] = implode(', ', $roles);
    $row['author'] = $entity->author ? $this->t('Yes') : $this->t('No');
    $row['revision_author'] = $entity->revision_author ? $this->t('Yes') : $this->t('No');
    $row['emails'] = $entity->emails;
    return $row + parent::buildRow($entity);
  }

}
