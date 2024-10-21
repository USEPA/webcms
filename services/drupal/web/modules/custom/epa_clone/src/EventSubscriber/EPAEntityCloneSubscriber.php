<?php

namespace Drupal\epa_clone\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\entity_clone\Event\EntityCloneEvent;
use Drupal\entity_clone\Event\EntityCloneEvents;
use Drupal\epa_forms\EpaFormsUniquifier;
use Drupal\node\Entity\Node;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 *
 */
class EPAEntityCloneSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new EPAEntityCloneSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager) {
    $this->entityTypeManager = $entity_manager;
  }

  /**
   *
   */
  public static function getSubscribedEvents() {
    $events[EntityCloneEvents::POST_CLONE][] = ['postClone', -1000];
    $events[EntityCloneEvents::PRE_CLONE][] = ['preClone'];
    return $events;
  }

  /**
   *
   */
  public function preClone(EntityCloneEvent $event) {
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $cloned_entity */
    $cloned_entity = $event->getClonedEntity();

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $original_entity */
    $original_entity = $event->getEntity();

    // Clear the machine name field on cloned entities.
    if ($original_entity->hasField('field_machine_name') && $original_entity->get('field_machine_name')->isEmpty()) {
      $cloned_entity->set('field_machine_name', '');
    }

    // Clear the scheduled transition field on cloned entities.
    if ($original_entity->hasField('field_scheduled_transition') && !$original_entity->get('field_scheduled_transition')->isEmpty()) {
      $cloned_entity->set('field_scheduled_transition', NULL);
    }

    // Clear the expiration date (sunset) field on cloned entities.
    if ($original_entity->hasField('field_expiration_date') && !$original_entity->get('field_expiration_date')->isEmpty()) {
      $cloned_entity->set('field_expiration_date', NULL);
    }

    // Clear the publish date field on cloned entities.
    if ($original_entity->hasField('field_publish_date') && !$original_entity->get('field_publish_date')->isEmpty()) {
      $cloned_entity->set('field_publish_date', NULL);
    }

    if ($cloned_entity instanceof Node) {
      foreach ($cloned_entity->getFieldDefinitions() as $field_id => $field_definition) {
        // Clone referenced webforms when a  node is cloned.
        if ($field_definition instanceof FieldConfigInterface && in_array($field_definition->getType(), ['webform'], TRUE)) {
          $field = $cloned_entity->get($field_id);
          /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $value */
          if ($field->count() > 0) {
            $referenced_entities = [];
            foreach ($field as $value) {
              // Check if we're not dealing with an entity
              // that has been deleted in the meantime.
              if (!$referenced_entity = $value->get('entity')->getTarget()) {
                continue;
              }
              /** @var \Drupal\Core\Entity\ContentEntityInterface $referenced_entity */
              $referenced_entity = $value->get('entity')
                ->getTarget()
                ->getValue();

              $cloned_reference = $referenced_entity->createDuplicate();
              /** @var \Drupal\entity_clone\EntityClone\EntityCloneInterface $entity_clone_handler */
              $entity_clone_handler = $this->entityTypeManager->getHandler($referenced_entity->getEntityTypeId(), 'entity_clone');

              $properties = [
                'id' => EpaFormsUniquifier::getFormIdForNode($cloned_entity),
                'label' => 'Cloned: ' . $referenced_entity->label(),
              ];
              $entity_clone_handler->cloneEntity($referenced_entity, $cloned_reference, $properties);

              $referenced_entities[] = $cloned_reference->id();
            }
            $cloned_entity->set($field_id, $referenced_entities);
          }
        }
      }
    }
  }

  /**
   *
   * @param \Drupal\entity\Event\EntityCloneEvent $event
   *   The entity_clone event.
   */
  public function postClone(EntityCloneEvent $event) {
    $cloned_entity = $event->getClonedEntity();

    // Prepend a "Cloned: " signifier to the title of cloned nodes.
    // Remove the appended " - Cloned" signifier to the title of cloned nodes.
    if ($cloned_entity instanceof Node) {
      $old_signifier = ' - Cloned';
      $new_signifier = 'Cloned: ';

      if ($cloned_entity->bundle() == 'faq') {
        $old = $cloned_entity->field_question->getString();
        $cloned_entity->field_question->setValue($new_signifier . ' ' . $old);
      }
      else {
        $old_pos = strpos($cloned_entity->getTitle(), $old_signifier);
        $new_title = $old_pos !== FALSE ?
          $new_signifier . substr($cloned_entity->getTitle(), 0, $old_pos) :
          $new_signifier . $cloned_entity->getTitle();
        $cloned_entity->setTitle($new_title);
      }
      $original_entity = $event->getEntity();

      // Ensure the new node is assigned to the same group as the old one.
      $groups = \Drupal::service('epa_web_areas.web_areas_helper')->getNodeReferencingGroups($original_entity);
      foreach ($groups as $group) {
        $group->addContent($cloned_entity, 'group_' . $original_entity->getEntityTypeId() . ':' . $original_entity->bundle());
      }

      $cloned_entity->save();
    }
  }

}
