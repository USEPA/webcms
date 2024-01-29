<?php

namespace Drupal\epa_workflow\Plugin\DanseRecipientSelection;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\danse\PayloadInterface;
use Drupal\danse\RecipientSelectionBase;
use Drupal\node\Entity\Node;

/**
 * Plugin implementation of the EPA Workflow DANSE recipient selection.
 *
 * @DanseRecipientSelection(
 *   id = "epa_workflow",
 *   label = @Translation("EPA Workflow"),
 *   description = @Translation("The DANSE recipient selection plugin for EPA Workflow.")
 * )
 */
class EpaWorkflow extends RecipientSelectionBase {

  /**
   * {@inheritdoc}
   */
  public function getRecipients(PayloadInterface $payload): array {
    $result = [];

    // Load the revision from the payload.
    $revision = $payload->getEntity();

    // Get the Revision's Group and the Group's Editor-in-Chief
    $groups = \Drupal::service('epa_web_areas.web_areas_helper')->getNodeReferencingGroups($revision);
    foreach($groups as $group) {
      if ($group->get('field_editor_in_chief')) {
        $result[] = $group->field_editor_in_chief->entity->id();
      }
    }

    return $result;
  }

}
