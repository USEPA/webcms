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

    $build['#theme'][] = 'epa_alerts';
    $build['#alertContext'] = 'public';

    $build['#attached']['drupalSettings']['epaAlerts']['tomeEnabled'] = \Drupal::moduleHandler()->moduleExists('tome');
    $build['#attached']['drupalSettings']['epaAlerts']['context'] = 'public';


    return $build;
  }

}
