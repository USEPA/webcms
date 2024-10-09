<?php

namespace Drupal\epa_workflow;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;

/**
 * Class EPANotificationEmailCron.
 *
 * Queues notification summaries to email to Editors & Deputy Editors in Chief.
 *
 * @package Drupal\epa_workflow
 */
class EPANotificationEmailCron {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * EPANotificationEmailCron constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MailManagerInterface $mail_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager = $mail_manager;
  }

  /**
   * Load group content.
   */
  public function loadGroupContent() {
    $group_content_entity_ids = $this->getGroupContentEntityIds();
    $group_content_entities = $this->entityTypeManager->getStorage('group_content')->loadMultiple($group_content_entity_ids);
    return $group_content_entities;
  }

  /**
   * Get group entity ids.
   */
  protected function getGroupContentEntityIds() {
    $query = $this->entityTypeManager->getStorage('group_content')
      ->getQuery()
      ->accessCheck(FALSE);
    return $query->execute();
  }

  /**
   * Get Editors in Chief and deputies.
   */
  protected function getEditorsInChief() {
    $group_contents = $this->loadGroupContent();
    $editors_in_chief = [];
    foreach ($group_contents as $group_content) {
      if ($group_content->get('field_editor_in_chief')) {
        $group_content_id = $group_content->id();
        $editors_in_chief[$group_content_id][] = $group_content->field_editor_in_chief->entity->id();

        // Get Deputy Editors in Chief.
        $group = $group_content->getGroup();
        $members = $group->getMembers();
        foreach ($members as $member) {
          if ($member->hasRole('deputy_editor_in_chief')) {
            $editors_in_chief[$group_content_id][] = $member->id();
          }
        }
      }
    }
    return $editors_in_chief;
  }

  /**
   * Send notification emails.
   */
  public function sendNotificationEmails() {
    $editors_in_chief = $this->getEditorsInChief();
  }

}
