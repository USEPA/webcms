<?php

namespace Drupal\epa_clone\EventSubscriber;

use Drupal\entity_clone\Event\EntityCloneEvent;
use Drupal\entity_clone\Event\EntityCloneEvents;
use Drupal\node\Entity\Node;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class EPAEntityCloneSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    $events[EntityCloneEvents::POST_CLONE][] = ['postClone', -1000];
    return $events;
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
        $cloned_entity->field_question->setValue($new_signifier. ' '. $old);
      }
      else {
        $old_pos = strpos($cloned_entity->getTitle(), $old_signifier);
        $new_title = $old_pos !== FALSE ?
          $new_signifier . substr($cloned_entity->getTitle(), 0, $old_pos) :
          $new_signifier . $cloned_entity->getTitle();
        $cloned_entity->setTitle($new_title);
      }
      $original_entity = $event->getEntity();

      // Clear the machine name field on cloned entities
      if ($original_entity->hasField('field_machine_name') && !empty($original_entity->get('field_machine_name'))) {
        $cloned_entity->set('field_machine_name', '');
      }

      // Ensure the new node is assigned to the same group as the old one
      $groups = \Drupal::service('epa_web_areas.web_areas_helper')->getNodeReferencingGroups($original_entity);
      foreach ($groups as $group) {
        $group->addContent($cloned_entity, 'group_' . $original_entity->getEntityTypeId() . ':' . $original_entity->bundle());
      }

      $cloned_entity->save();
    }
  }
}
