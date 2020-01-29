<?php

namespace Drupal\epa_alerts\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an 'PublicAlertsBlock' block.
 *
 * @Block(
 *  id = "public_alerts_block",
 *  admin_label = @Translation("EPA public alerts"),
 * )
 */
class PublicAlertsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $build = [];

    $build['public_alerts_block'] = [
      '#theme' => 'epa_alerts__public',
      '#attached' => ['library' => 'epa_alerts/publicAlerts'],
    ];

    return $build;
  }

}
