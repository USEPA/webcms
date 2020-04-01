<?php

namespace Drupal\epa_content_tracker\Plugin\views\wizard;

use Drupal\views\Plugin\views\wizard\WizardPluginBase;

/**
 * Defines a wizard for the watchdog table.
 *
 * @ViewsWizard(
 *   id = "epa_content_tracker",
 *   module = "epa_content_tracker",
 *   base_table = "epa_content_tracker",
 *   title = @Translation("EPA Content Tracker")
 * )
 */
class EpaContentTracker extends WizardPluginBase {

  /**
   * Set the created column.
   *
   * @var string
   */
  protected $changeColumn = 'timestamp';

  /**
   * {@inheritdoc}
   */
  protected function defaultDisplayOptions() {
    $display_options = parent::defaultDisplayOptions();

    // Add permission-based access control.
    $display_options['access']['type'] = 'perm';
    $display_options['access']['options']['perm'] = 'access content tracker';

    return $display_options;
  }

}
