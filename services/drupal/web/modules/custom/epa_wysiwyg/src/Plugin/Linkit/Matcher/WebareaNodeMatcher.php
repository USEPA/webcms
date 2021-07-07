<?php

namespace Drupal\epa_wysiwyg\Plugin\Linkit\Matcher;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupContent;
use Drupal\group\GroupMembershipLoader;
use Drupal\linkit\Plugin\Linkit\Matcher\NodeMatcher;
use Drupal\linkit\SubstitutionManagerInterface;
use Drupal\linkit\Suggestion\SuggestionCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides linkit matcher for the node entity type, restricted by web area.
 *
 * @Matcher(
 *   id = "entity:webarea_node",
 *   label = @Translation("Web Area Content"),
 *   target_entity = "node",
 *   provider = "node"
 * )
 */
class WebareaNodeMatcher extends NodeMatcher {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The target entity type ID.
   *
   * @var string
   */
  protected $targetType;

  /**
   * The substitution manager.
   *
   * @var \Drupal\linkit\SubstitutionManagerInterface
   */
  protected $substitutionManager;

  /**
   * The group membership loader.
   *
   * @var \Drupal\group\GroupMembershipLoader
   */
  protected $groupMembershipLoader;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityRepositoryInterface $entity_repository, ModuleHandlerInterface $module_handler, AccountInterface $current_user, SubstitutionManagerInterface $substitution_manager, GroupMembershipLoader $group_membership_loader) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $database, $entity_type_manager, $entity_type_bundle_info, $entity_repository, $module_handler, $current_user, $substitution_manager);

    if (empty($plugin_definition['target_entity'])) {
      throw new \InvalidArgumentException("Missing required 'target_entity' property for a matcher.");
    }
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityRepository = $entity_repository;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $current_user;
    $this->targetType = $plugin_definition['target_entity'];
    $this->substitutionManager = $substitution_manager;
    $this->groupMembershipLoader = $group_membership_loader;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity.repository'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('plugin.manager.linkit.substitution'),
      $container->get('group.membership_loader'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute($string) {
    $suggestions = new SuggestionCollection();
    $query = $this->buildEntityQuery($string);
    $query_result = $query->execute();
    $url_results = $this->findEntityIdByUrl($string);
    $result = array_merge($query_result, $url_results);

    // If no results, return an empty suggestion collection.
    if (empty($result)) {
      return $suggestions;
    }

    // Get the group ids for groups the current user is a member of.
    $user_groups = $this->groupMembershipLoader->loadByUser();
    $user_group_ids = array_map(
      function ($group) {
        return $group->getGroup()->id();
      }, $user_groups
    );

    $entities = $this->entityTypeManager->getStorage($this->targetType)->loadMultiple($result);

    foreach ($entities as $entity) {
      // Check the access against the defined entity access handler.
      /** @var \Drupal\Core\Access\AccessResultInterface $access */
      $access = $entity->access('view', $this->currentUser, TRUE);

      if (!$access->isAllowed()) {
        continue;
      }

      $entity = $this->entityRepository->getTranslationFromContext($entity);
      // Get the group ids for groups the entity is a member of.
      $entity_groups = GroupContent::loadByEntity($entity);
      $entity_group_ids = array_map(
        function ($group) {
          return $group->getGroup()->id();
        }, $entity_groups
      );

      // Add the suggestion if the entity belongs to one of the user's groups.
      if (array_intersect($entity_group_ids, $user_group_ids)) {
        $entity = $this->entityRepository->getTranslationFromContext($entity);
        $suggestion = $this->createSuggestion($entity);
        $suggestions->addSuggestion($suggestion);
      }
    }

    return $suggestions;
  }

}
