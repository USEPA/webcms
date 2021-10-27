<?php

namespace Drupal\epa_core\Plugin\views\style;

use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * USWDS Collection style plugin.
 *
 * @ViewsStyle(
 *   id = "epa_core_uswds_collection",
 *   title = @Translation("USWDS Collection"),
 *   help = @Translation("Foo style plugin help."),
 *   theme = "views_style_epa_core_uswds_collection",
 *   display_types = {"normal"}
 * )
 */
class UswdsCollection extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesRowClass = FALSE;

}
