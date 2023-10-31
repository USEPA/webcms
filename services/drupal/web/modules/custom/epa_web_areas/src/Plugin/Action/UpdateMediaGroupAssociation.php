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
 *   id = "epa_web_areas_update_media_group_association",
 *   label = @Translation("Change Web Area association of Media"),
 *   type = "media",
 *   category = @Translation("Custom")
 * )
 *
 * @DCG
 * For a simple updating entity fields consider extending FieldUpdateActionBase.
 */
class UpdateMediaGroupAssociation extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Group membership loader service.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $groupMembershipLoader;


  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('group.membership_loader'),
    );
  }

  /**
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountProxyInterface $current_user, GroupMembershipLoaderInterface $group_membership_loader) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->groupMembershipLoader = $group_membership_loader;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'updated_group' => NULL
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['updated_group'] = [
      '#title' => $this->t('Updated Group'),
      '#type' => 'select',
      '#options' => $this->getGroupOptions(),
      '#required' => TRUE,
      '#default_value' => $this->configuration['updated_group'],
    ];
    return $form;
  }

  public function getGroupOptions() {
    $options = [];
    if (array_intersect($this->currentUser->getRoles(), ['administrator', 'system_webmaster'])) {
      $groups = Group::loadMultiple();
      foreach ($groups as $group) {
        $options[$group->id()] = "{$group->label()} ({$group->id()})";
      }
    }
    else {
      // Get all groups the current user belongs to
      $memberships = $this->groupMembershipLoader->loadByUser();
      foreach ($memberships as $group) {
        $options[$group->getGroup()->id()] = "{$group->getGroup()->label()} ({$group->getGroup()->id()})";
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['updated_group'] = $form_state->getValue('updated_group');
  }

  /**
   * {@inheritdoc}
   */
  public function access($media, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\media\MediaInterface $media */
    $access = $media->access('update', $account, TRUE);
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute($media = NULL) {
    // Get the GroupContent from the node and update it using the new group from the 'updated_group' configuration.
    $group_contents = GroupContent::loadByEntity($media);
    if ($group_contents) {
      foreach ($group_contents as $group_content) {
        $group_content->get('gid')->setValue($this->configuration['updated_group']);
        $group_content->save();
      }
    } else {
      $group_content = GroupContent::create([
        'type' => 'web_area-group_media-',
        'uid' => 0,
        'gid' => $this->configuration['updated_group'],
        'entity_id' => $media->id(),
        'label' => $media->name(),
      ]);
      $group_content->save();
    }
  }

}
