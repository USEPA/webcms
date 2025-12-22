<?php

namespace Drupal\epa_wysiwyg\Plugin\Linkit\Matcher;

use Drupal\group\Entity\GroupContent;
use Drupal\group\GroupMembershipLoader;
use Drupal\linkit\Plugin\Linkit\Matcher\NodeMatcher;
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
   * The group membership loader.
   *
   * @var \Drupal\group\GroupMembershipLoader
   */
  protected GroupMembershipLoader $groupMembershipLoader;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    /** @var static $instance */
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition
    );

    // Inject ONLY the custom dependency.
    $instance->groupMembershipLoader = $container->get('group.membership_loader');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($string) {
    $suggestions = new SuggestionCollection();

    // Build the standard Linkit entity query.
    $query = $this->buildEntityQuery($string);
    $query_result = $query->execute();

    // This now works correctly in Linkit 7.
    $url_results = $this->findEntityIdByUrl($string);

    $result = array_merge($query_result, $url_results);

    if (empty($result)) {
      return $suggestions;
    }

    // Get group IDs for the current user.
    $user_groups = $this->groupMembershipLoader->loadByUser();
    $user_group_ids = array_map(
      static function ($membership) {
        return $membership->getGroup()->id();
      },
      $user_groups
    );

    $entities = $this->entityTypeManager
      ->getStorage($this->targetType)
      ->loadMultiple($result);

    foreach ($entities as $entity) {
      // Respect entity access.
      $access = $entity->access('view', $this->currentUser, TRUE);
      if (!$access->isAllowed()) {
        continue;
      }

      $entity = $this->entityRepository->getTranslationFromContext($entity);

      // Get group IDs for the entity.
      $entity_groups = GroupContent::loadByEntity($entity);
      $entity_group_ids = array_map(
        static function ($group_content) {
          return $group_content->getGroup()->id();
        },
        $entity_groups
      );

      // Only suggest entities that share a group with the user.
      if (array_intersect($entity_group_ids, $user_group_ids)) {
        $suggestions->addSuggestion(
          $this->createSuggestion($entity)
        );
      }
    }

    return $suggestions;
  }

}
