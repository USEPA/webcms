<?php

namespace Drupal\epa_web_areas\Plugin\views\filter;

use Drupal\search_api\Plugin\views\filter\SearchApiFilterTrait;

/**
 * Filters nodes based on user's groups.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("epa_search_api_web_areas_users_groups")
 */
class SearchApiUsersGroupsFilter extends UsersGroupsFilter {
  use SearchApiFilterTrait;

}
