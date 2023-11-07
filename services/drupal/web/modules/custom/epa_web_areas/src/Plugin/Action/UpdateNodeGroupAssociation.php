<?php

namespace Drupal\epa_web_areas\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupContent;
use Drupal\group\Entity\GroupContentType;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the 'Update Group Association' action.
 *
 * @Action(
 *   id = "epa_web_areas_update_node_group_association",
 *   label = @Translation("Change Web Area association of nodes"),
 *   type = "node",
 *   category = @Translation("Custom")
 * )
 */
class UpdateNodeGroupAssociation extends UpdateGroupAssociationBase {

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
//      // @TODO: Build this out and build plugin type correctly
//      GroupContent::create([
//        'type' => 'web_area-group_node-' . $entity->bundle(),
//        'uid' => 0,
//        'gid' => $this->configuration['updated_group'],
//        'entity_id' => $entity->id(),
//        'label' => $entity->getTitle(),
//        'entity_type' => 'node',
//        'group_type' => 'web_area',
//      ])
//      ->save();
//    }
//  }

}
