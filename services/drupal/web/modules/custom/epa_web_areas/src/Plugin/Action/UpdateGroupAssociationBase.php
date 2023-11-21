<?php

namespace Drupal\epa_web_areas\Plugin\Action;

use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupContent;
use Drupal\group\GroupMembershipLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class UpdateGroupAssociationBase extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

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
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;


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
      $container->get('config.factory'),
      $container->get('messenger'),
    );
  }

  /**
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountProxyInterface $current_user, GroupMembershipLoaderInterface $group_membership_loader, ConfigFactoryInterface $config_factory, MessengerInterface $messenger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->groupMembershipLoader = $group_membership_loader;
    $this->configFactory = $config_factory;
    $this->messenger = $messenger;
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
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $config = $this->configFactory->get('epa_web_areas.allowed_bulk_change');

    // $object is not allowed based on config.
    if ($config->get($object->getEntityTypeId()) && !in_array($object->bundle(), $config->get($object->getEntityTypeId()))) {
      $this->context['denied'][] = $object->getTitle();
      // If it's not in the array of our configuration we do not allow it.
//      return $return_as_object ? new AccessResultForbidden('You cannot update the Web Area association for ' . $object->bundle() . '. Contact Web_CMS_Support@epa.gov for help.') : FALSE;
      return $return_as_object ? new AccessResultForbidden() : FALSE;
    }

    // News Releases, Perspectives, and Speeches & Remarks are special in that
    // they are only allowed if enabled on the specific Web Area.
    // If the $object is one of those three bundles we need to check that
    // bundle is allowed on the target Group selected in the VBO form.
    $special_types = [
      'news_release',
      'perspective',
      'speeches',
    ];
    if ($object->getEntityTypeId() == 'node' && in_array($object->bundle(), $special_types)) {
      $group = Group::load($this->configuration['updated_group']);
      if (!$this->groupAllowsBundle($object->bundle())) {
        $this->context['special_denied'][] = $object->getTitle();
        return $return_as_object ? new AccessResultForbidden() : FALSE;
      }
    }

    // Check if the object we're updating has a group associated with it, i.e GroupContent entity.
    // If it does not then we need to check if the current user is an admin or system_webmaster as those
    // are the only users to allow associated 'orphaned' contnet with a new Web Area.
    $group_contents = GroupContent::loadByEntity($object);
    $allowed_roles = [
      'administrator',
      'system_webmaster',
    ];
    if ($group_contents) {
      $access = $object->access('update', $account, TRUE);
      return $return_as_object ? $access : $access->isAllowed();
    }
    else if (array_intersect($account->getRoles(), $allowed_roles)) {
      // Means the object is not currently associated with a Group.
      // Per logic of https://forumone.atlassian.net/browse/EPAD8-2249 only
      // admins and system_webmaster users should have this access to this
      $access = $object->access('update', $account, TRUE);
      return $return_as_object ? $access : $access->isAllowed();
    }

    $message = new TranslatableMarkup('Your account does not have access to add a web area association to this content. Contact Web_CMS_Support@epa.gov for help.');
    return $return_as_object ? new AccessResultForbidden($message->render()) : FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function executeMultiple(array $entities) {
    foreach ($entities as $entity) {
      $this->execute($entity);
    }

    if (isset($this->context['denied']) && !empty($this->context['denied'])) {
      $denied_message = 'Denied: Unable to move ';
      for ($i = 0; $i < count($this->context['denied']); $i++) {
        $denied_message .= $this->context['denied'][$i] . ', ';
      }
      $denied_message = trim($denied_message);

      if (count($this->context['denied']) > 5) {
        $denied_message .= ' and more...';
      }

      $denied_message .= ' as you do not have access to these nodes.';

      $this->messenger->addError($denied_message);
    }

    if (isset($this->context['special_denied']) && !empty($this->context['special_denied'])) {
      $group = Group::load($this->configuration['updated_group']);
      $special_denied_message = 'Denied: Unable to move ';
      for ($i = 0; $i < count($this->context['special_denied']); $i++) {
        $special_denied_message .= $this->context['special_denied'][$i] . ', ';
      }
      $special_denied_message = trim($special_denied_message);

      if (count($this->context['special_denied']) > 5) {
        $special_denied_message .= ' and more... ';
      }
      $special_denied_message = trim($special_denied_message);
      $special_denied_message .= " as the {$group->label()} Web Area does not allow creating these nodes.";
      $this->messenger->addError($special_denied_message);
    }

    if (isset($this->context['success']) && !empty($this->context['success'])) {
      $success_message = 'Successfully moved ';
      for ($i = 0; $i < count($this->context['success']); $i++) {
        $success_message .= $this->context['success'][$i] . ', ';
      }

      if (count($this->context['success']) > 5) {
        $success_message .= ' and more...';
      }
      $success_message = trim($success_message);

      $success_message .= " to the new {$this->context['target_group']->label()} Web Area. Review the menu links from the previously associated Web Area.";
      $this->messenger->addStatus($success_message);
    }

  }


  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    // Get the GroupContent from the node and update it using the new group from the 'updated_group' configuration.
    $group_contents = GroupContent::loadByEntity($entity);
    if ($group_contents) {
      foreach ($group_contents as $group_content) {
        $group_content->get('gid')->setValue($this->configuration['updated_group']);
        $group_content->save();
      }
      $this->context['success'][] = $entity->getEntityTypeId() == 'node' ? $entity->getTitle() : $entity->label();
    }
    else {
      $values = [
        'type' => $entity->getEntityTypeId() == 'node' ? 'web_area-group_node-' . $entity->bundle() : 'web_area-group_media-' . $entity->bundle(),
        'uid' => 0,
        'gid' => $this->configuration['updated_group'],
        'entity_id' => $entity->id(),
        'entity_type' => $entity->getEntityTypeId(),
        'label' =>  $entity->getEntityTypeId() == 'node' ? $entity->getTitle() : $entity->label(),
      ];
      // Means it was never associated with a group
      GroupContent::create($values)->save();
      $this->context['success'][] = $entity->getEntityTypeId() == 'node' ? $entity->getTitle() : $entity->label();
    }
  }


  /**
   * Method for checking our special content types against the Group to see if they are allowed.
   *
   * @param string $bundle
   *
   * @return mixed|true
   */
  public function groupAllowsBundle(string $bundle): mixed {
    $group = Group::load($this->configuration['updated_group']);

    return match ($bundle) {
      'news_release' => filter_var($group->get('field_allow_news_releases')->value, FILTER_VALIDATE_BOOLEAN),
      'perspective' => filter_var($group->get('field_allow_perspectives')->value, FILTER_VALIDATE_BOOLEAN),
      'speeches' => filter_var($group->get('field_allow_speeches')->value, FILTER_VALIDATE_BOOLEAN),
      default => TRUE,
    };

  }
}
