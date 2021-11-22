<?php

namespace Drupal\epa_web_areas\Plugin\views\filter;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\ManyToOne;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Filters nodes based on user's groups
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("epa_web_areas_users_groups")
 */
class UsersGroupsFilter extends ManyToOne {

  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->valueTitle = $this->t('Filter on Groups the current user belongs to.');
    $this->definition['options callback'] = [$this, 'generateOptions'];
    $this->currentDisplay = $view->current_display;
  }

  public function query() {
    if (!empty($this->value)) {
      $groups = $this->getAllGroupsByUser();
      if (!empty($groups)) {
        // @TODO: Why do I have to use the alias field? Do I need to add a relationship in hook_views_data_alter()?
        $this->query->addWhere('AND', 'group_content_field_data_node_field_data.gid', $groups, 'IN');
      }
    }
  }

  public function generateOptions() {
    return [
      $this->t('In My Groups'),
    ];
  }

  private function getAllGroupsByUser() {
    $groups = [];
    /** @var \Drupal\group\GroupMembershipLoaderInterface $grp_membership_service */
    $grp_membership_service = \Drupal::service('group.membership_loader');
    $memberships = $grp_membership_service->loadByUser();
    foreach ($memberships as $group) {
      $groups[] = $group->getGroup()->id();
    }

    return $groups;
  }

}
