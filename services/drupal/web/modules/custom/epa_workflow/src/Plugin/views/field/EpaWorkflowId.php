<?php

namespace Drupal\epa_workflow\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Provides a field for views to show the reference ID for danse notifications.
 *
 * @ViewsField("epa_workflow_id")
 */
class EpaWorkflowId extends FieldPluginBase {

  /**
   * The node table.
   */
  protected ?string $node_table;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    $configuration = [
      'table' => 'node_field_data',
      'field' => 'nid',
      'left_table' => 'danse_event',
      'left_formula' => 'SUBSTRING(danse_event_danse_notification.reference, POSITION(\'-\' IN danse_event_danse_notification.reference)+1, 100)',
    ];
    $join = \Drupal::service('plugin.manager.views.join')->createInstance('standard', $configuration);

    $this->node_table = $this->query->ensureTable('node_field_data', $this->relationship, $join);

    $this->field_alias = $this->query->addField($this->node_table, 'nid');
  }

}
