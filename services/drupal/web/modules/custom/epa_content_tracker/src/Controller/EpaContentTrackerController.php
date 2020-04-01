<?php

namespace Drupal\epa_content_tracker\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for EPA Content Tracker routes.
 */
class EpaContentTrackerController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
