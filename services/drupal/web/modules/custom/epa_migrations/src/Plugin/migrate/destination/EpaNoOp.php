<?php

namespace Drupal\epa_migrations\Plugin\migrate\destination;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\destination\DestinationBase;
use Drupal\migrate\Row;

/**
 * Provides noop destination plugin.
 *
 * Sometimes you want to use the migrate system for more, or less, that it was
 * designed for and this means not having anything to do in the destination
 * plugin.  e.g. your migration is too far in to back out and you need to 'fix'
 * something. A quick lightweight migration where the work happens in the
 * process section could do this.
 *
 * This destination plugin is a 'quickfix' to the `null` destination plugin
 * always throwing false for `requirements_met`. I think this also sounds more
 * 'correct' in describing what the plugin does.
 *
 * @MigrateDestination(
 *   id = "epa_noop"
 * )
 *
 * Plugin provided by:
 * https://www.drupal.org/project/drupal/issues/2857104#comment-12687410
 */
class EpaNoOp extends DestinationBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->supportsRollback = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'unsigned' => FALSE,
        'size' => 'big',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function fields(MigrationInterface $migration = NULL) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    return $row->getSourceIdValues() ?? TRUE;
  }

}
