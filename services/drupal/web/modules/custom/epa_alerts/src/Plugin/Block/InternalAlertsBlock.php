<?php

namespace Drupal\epa_alerts\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an 'InternalAlertsBlock' block.
 *
 * @Block(
 *  id = "internal_alerts_block",
 *  admin_label = @Translation("EPA internal alerts"),
 * )
 */
class InternalAlertsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $build = [];

    $build['internal_alerts_block'] = [
      '#theme' => 'epa_alerts__internal',
      '#attached' => ['library' => 'epa_alerts/internalAlerts'],
    ];

    return $build;
  }

}
