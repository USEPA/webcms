<?php

namespace Drupal\epa_wysiwyg\Plugin\Linkit\Matcher;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupContent;
use Drupal\group\GroupMembershipLoader;
use Drupal\linkit\Plugin\Linkit\Matcher\NodeMatcher;
use Drupal\linkit\Suggestion\SuggestionCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Linkit matcher restricted to the user's Web Areas.
 *
 * @Matcher(
 *   id = "entity:webarea_node",
 *   label = @Translation("Web Area Content"),
 *   target_entity = "node",
 *   provider = "node"
 * )
 */
class WebareaNodeMatcher extends NodeMatcher {

  protected GroupMembershipLoader $groupMembershipLoader;
  protected $currentUser;

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

    $instance->groupMembershipLoader = $container->get('group.membership_loader');
    $instance->currentUser = $container->get('current_user');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($string) {
    $suggestions = new SuggestionCollection();

    // Load user's groups explicitly.
    $memberships = $this->groupMembershipLoader->loadByUser($this->currentUser);
    if (empty($memberships)) {
      return $suggestions;
    }

    $user_group_ids = array_map(
      static fn($m) => $m->getGroup()->id(),
      $memberships
    );

    $query = $this->buildEntityQuery($string);
    $result = array_merge(
      $query->execute(),
      $this->findEntityIdByUrl($string)
    );

    if (empty($result)) {
      return $suggestions;
    }

    $entities = $this->entityTypeManager
      ->getStorage('node')
      ->loadMultiple($result);

    foreach ($entities as $entity) {
      if (!$entity->access('view', $this->currentUser)) {
        continue;
      }

      $entity_groups = GroupContent::loadByEntity($entity);
      $entity_group_ids = array_map(
        static fn($gc) => $gc->getGroup()->id(),
        $entity_groups
      );

      if (array_intersect($entity_group_ids, $user_group_ids)) {
        $suggestions->addSuggestion($this->createSuggestion($entity));
      }
    }

    return $suggestions;
  }

}
