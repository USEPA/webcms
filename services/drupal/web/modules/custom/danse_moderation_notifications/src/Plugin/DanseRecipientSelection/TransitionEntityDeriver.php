<?php

namespace Drupal\danse_moderation_notifications\Plugin\DanseRecipientSelection;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Deriver for DANSE recipient plugins for each entity.
 */
class TransitionEntityDeriver extends DeriverBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];

    $entity_type_id = 'danse_moderation_notifications';
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);

    // Load all entities.
    $entities = $entity_storage->loadMultiple();

    foreach ($entities as $entity) {
      // Customize this according to your entity structure.
      $label = $entity->label();
      $this->derivatives[$entity->id()] = [
          'entity_id' => $entity->id(),
          'label' => $this->t('Entity @label', ['@label' => $label]),
          'description' => t('Selects users associated with entity %label.', ['%label' => $label]),
        ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
