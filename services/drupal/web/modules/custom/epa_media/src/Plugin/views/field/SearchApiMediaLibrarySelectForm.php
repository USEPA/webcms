<?php

namespace Drupal\epa_media\Plugin\views\field;


use Drupal\media_library\Plugin\views\field\MediaLibrarySelectForm;
use Drupal\search_api\Plugin\views\field\SearchApiFieldTrait;

/**
 * Defines a field that outputs a checkbox and form for selecting media.
 *
 * @ViewsField("search_api_media_library_select_form")
 *
 * @internal
 *   Plugin classes are internal.
 */
class SearchApiMediaLibrarySelectForm extends MediaLibrarySelectForm {
  use SearchApiFieldTrait;
}
