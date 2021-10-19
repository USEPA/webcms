<?php

namespace Drupal\epa_media;

use Drupal\media_library\MediaLibraryState;
use Drupal\media_library\MediaLibraryUiBuilder;

class EpaMediaLibraryUiBuilder extends MediaLibraryUiBuilder {
  protected function getViewId(MediaLibraryState $state): string {
    return 'media_library_search_api';
  }
}
