<?php

namespace Drupal\epa_web_areas\Plugin\Action;

use Drupal\group\Entity\GroupContent;

/**
 * Provides the 'Update Group Association' action.
 *
 * @Action(
 *   id = "epa_web_areas_update_media_group_association",
 *   label = @Translation("Change Web Area association of Media"),
 *   type = "media",
 *   category = @Translation("Custom")
 * )
 */
class UpdateMediaGroupAssociation extends UpdateGroupAssociationBase {

//  /**
//   * {@inheritdoc}
//   * @todo: refactor this to not be unique per entity "type". We should be able to get the "type" based on the Group Content plugin, but ran out of time.
//   */
//  public function execute($entity = NULL) {
//    // Get the GroupContent from the node and update it using the new group from the 'updated_group' configuration.
//    $group_contents = GroupContent::loadByEntity($entity);
//    if ($group_contents) {
//      foreach ($group_contents as $group_content) {
//        $group_content->get('gid')->setValue($this->configuration['updated_group']);
//        $group_content->save();
//      }
//    }
//    else {
//      // Means it was never associated with a group
//      GroupContent::create([
//        'type' => 'web_area-group_media-' . $entity->bundle(),
//        'uid' => 0,
//        'gid' => $this->configuration['updated_group'],
//        'entity_id' => $entity->id(),
//        'label' => $entity->label(),
//      ])->save();
//
//    }
//  }
}
