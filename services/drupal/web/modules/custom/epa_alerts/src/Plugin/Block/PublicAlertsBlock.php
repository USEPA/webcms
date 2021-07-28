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

    // Must manually add this since we're not directly rendering the view so the
    // views_ajax_get module doesn't get an opportunity to attach it dynamically.
    $build['#attached']['drupalSettings']['viewsAjaxGet']['public_alerts'] = 'public_alerts';

    $build['#attached']['library'][] = 'epa_alerts/epaAlerts';
    $build['#attached']['drupalSettings']['epaAlerts']['context'] = 'public';

    return $build;
  }

}
