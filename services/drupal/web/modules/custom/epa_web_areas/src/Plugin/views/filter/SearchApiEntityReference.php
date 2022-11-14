<?php

namespace Drupal\epa_web_areas\Plugin\views\filter;

use Drupal\search_api\Plugin\views\filter\SearchApiFilterTrait;
use Drupal\views\Plugin\views\filter\EntityReference;

/**
 * Defines a filter for filtering on entity references.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("search_api_entity_reference")
 */
class SearchApiEntityReference extends EntityReference {

  use SearchApiFilterTrait;
}
