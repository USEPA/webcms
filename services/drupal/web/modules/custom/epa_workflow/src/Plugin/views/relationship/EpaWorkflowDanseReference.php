<?php

namespace Drupal\epa_workflow\Plugin\views\relationship;

use Drupal\views\Plugin\views\relationship\RelationshipPluginBase;

/**
 * Implementation of custom danse relationship plugin.
 *
 * @ingroup views_relationship_handlers
 *
 * @ViewsRelationship("epa_workflow_danse_reference")
 */
class EpaWorkflowDanseReference extends RelationshipPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    $def = $this->definition;
    $def['table'] = $this->definition['base'];
    $def['field'] = $this->definition['base field'];
    $def['left_table'] = $this->tableAlias;
    $left_table_column = $this->tableAlias . '.' . $this->realField;
    $def['left_formula'] = 'SUBSTRING(' . $left_table_column . ', POSITION(\'-\' IN ' . $left_table_column . ')+1, 100)';
    $def['adjusted'] = TRUE;
    if (!empty($this->options['required'])) {
      $def['type'] = 'INNER';
    }

    $join = \Drupal::service('plugin.manager.views.join')->createInstance('standard', $def);

    // Use a short alias for this.
    $alias = $def['table'] . '_' . $this->table;

    $this->alias = $this->query->addRelationship($alias, $join, $this->definition['base'], $this->relationship);

  }

}
