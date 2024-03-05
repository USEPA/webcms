<?php

namespace Drupal\epa_workflow\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Template\Attribute;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a follow widget block.
 *
 * @Block(
 *   id = "epa_workflow_follow_widget",
 *   admin_label = @Translation("Follow Widget"),
 *   category = @Translation("Custom"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", required = TRUE, label = @Translation("Node")),
 *   }
 * )
 */
class FollowWidgetBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The Drupal flag machine name for opting in to 'watching' the node.
   */
  const NOTIFICATION_OPT_IN = 'notification_opt_in';

  /**
   * The Drupal permission used for this block's access check.
   */
  const NOTIFICATION_OPT_IN_PERMISSION = 'flag notification_opt_in';

  /**
   * The entity_type manager service.
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The flag service.
   *
   * @var FlagServiceInterface
   */
  protected $flag;

  /**
   * The current user.
   *
   * @var AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new FollowWidgetBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity_type manager service.
   * @param FlagServiceInterface $flag
   *   The flag service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, FlagServiceInterface $flag, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->flag = $flag;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('flag'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    // Only allow users access to this block if they have the permission to use the 'notification_opt_in' flag.
    return AccessResult::allowedIfHasPermission($account, self::NOTIFICATION_OPT_IN_PERMISSION);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var NodeInterface $node */
    $entity = $this->getContextValue('node');

    // Get the flag as a link item for the notification opt-in.
    /** @var \Drupal\flag\Entity\Flag $flag */
    $flag = $this->entityTypeManager
      ->getStorage('flag')
      ->load(self::NOTIFICATION_OPT_IN);

    // Build the flag link.
    $flag_link_plugin = $flag->getLinkTypePlugin();
    $flag_link = $flag_link_plugin->getAsFlagLink($flag, $entity);

    /** @var User[] $users */
    $users = $this->flag->getFlaggingUsers($entity);
    $user_list = $this->getFlaggingUsersList($users);

    $build['flag_link'] = [
      '#theme' => 'epa_follow_widget',
      '#flag_link' => $flag_link,
      '#flag_users' => $user_list,
    ];

    // @todo: Figure out if there's a way we can cache based on the list of users who have flagged the node.
    $build['#cache']['max-age'] = 0;

    return $build;
  }

  /**
   * Builds an item_list render array of user's names.
   *
   * @param AccountInterface[] $users
   *   The array of users who have currently flagged the entity.
   * @return array|string[]
   *   The item list render array of user's names.
   */
  private function getFlaggingUsersList(array $users) {
    $user_list = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => [],
    ];

    foreach ($users as $user) {
      $name = $this->getUsersName($user);
      // If the user in question is the current user, add the "(Me)" identifier to the item.
      if ($this->currentUser->id() == $user->id()) {
        $name .= ' (Me)';
        // Always make this the first user.
        array_unshift($user_list['#items'], $name);
      }
      else {
        $user_list['#items'][] = $name;
      }
    }

    return $user_list;
  }

  /**
   * Helper to return the value of the user's name we want.
   *
   * @param UserInterface $user
   *   The user to get the name for.
   */
  private function getUsersName(UserInterface $user) {
    if (!$user->get('field_full_name')->isEmpty()) {
      return $user->get('field_full_name')->value;
    }

    return "{$user->getAccountName()} ({$user->getEmail()})";
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheContexts() {
    // Vary by user context.
    return ['user'];
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['node:' . $this->getContextValue('node')->id()]);
  }
}
