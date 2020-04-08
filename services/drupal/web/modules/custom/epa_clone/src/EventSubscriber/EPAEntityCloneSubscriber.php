<?php

namespace Drupal\epa_clone\EventSubscriber;

use Drupal\entity_clone\Event\EntityCloneEvent;
use Drupal\entity_clone\Event\EntityCloneEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class EPAEntityCloneSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    $events[EntityCloneEvents::POST_CLONE][] = ['generateGroupContent', -1000];
    return $events;
  }

  /**
   * Assign content to its respective group after cloning.
   *
   * @param \Drupal\entity\Event\EntityCloneEvent $event
   *   The entity_clone event.
   */
  public function generateGroupContent(EntityCloneEvent $event) {
    $entity = $event->getEntity();
    $cloned_entity = $event->getClonedEntity();
    $groups = \Drupal::service('epa_web_areas.web_areas_helper')
      ->getNodeReferencingGroups($entity);

    foreach ($groups as $group) {
      $group->addContent($cloned_entity, 'group_' . $entity->getEntityTypeId() . ':' . $entity->bundle());
    }
  }
}
