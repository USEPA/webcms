<?php

namespace Drupal\epa_workflow\EventSubscriber;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\core_event_dispatcher\Event\Entity\EntityUpdateEvent;
use Drupal\danse_content_moderation\EventSubscriber\ContentModerationEventSubscriber;

class EPAContentModerationEventSubscriber extends ContentModerationEventSubscriber {

  /**
  * {@inheritDoc}
  */
  public function onEntityUpdate(EntityUpdateEvent $event) {
    $entity = $event->getEntity();
    if ($entity instanceof ContentEntityInterface && $entity->getEntityTypeId() === 'content_moderation_state') {
      if (!$entity->isSyncing()) {
        parent::onEntityUpdate($event);
      }
    }
  }
}
