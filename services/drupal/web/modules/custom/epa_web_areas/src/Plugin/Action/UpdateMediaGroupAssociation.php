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

}
