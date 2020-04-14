<?php

namespace Drupal\epa_clone\EventSubscriber;

use Drupal\entity_clone\Event\EntityCloneEvent;
use Drupal\entity_clone\Event\EntityCloneEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class EPAEntityCloneSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    $events[EntityCloneEvents::POST_CLONE][] = ['generateGroupContent', -1000];
    $events[EntityCloneEvents::POST_CLONE][] = ['clearMachineName', -1000];
    return $events;
  }

  /**
   * - Assign content to its respective group after cloning.
   *
   * @param \Drupal\entity\Event\EntityCloneEvent $event
   *   The entity_clone event.
   */
  public function generateGroupContent(EntityCloneEvent $event) {
    $entity = $event->getEntity();
    $cloned_entity = $event->getClonedEntity();
    $groups = \Drupal::service('epa_web_areas.web_areas_helper')->getNodeReferencingGroups($entity);
    foreach ($groups as $group) {
      $group->addContent($cloned_entity, 'group_' . $entity->getEntityTypeId() . ':' . $entity->bundle());
    }
  }

  /**
   * - Clear the machine name field on the cloned entity.
   * @param \Drupal\entity_clone\Event\EntityCloneEvent $event
   *   The entity_clone event.
   */
  public function clearMachineName(EntityCloneEvent $event) {
    $entity = $event->getEntity();
    $cloned_entity = $event->getClonedEntity();
    if ($entity->getEntityTypeId() === 'node' && $entity->hasField('field_machine_name') && !empty($entity->get('field_machine_name'))) {
      $cloned_entity->set('field_machine_name', '');
    }
    $cloned_entity->save();
  }
}
