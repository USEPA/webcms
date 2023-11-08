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

}
