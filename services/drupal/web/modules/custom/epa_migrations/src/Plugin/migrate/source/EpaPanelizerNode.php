<?php

namespace Drupal\epa_migrations\Plugin\migrate\source;

use Drupal\node\Plugin\migrate\source\d7\Node;

/**
 * Load Nodes that will be migrated into Layout Builder.
 *
 * @MigrateSource(
 *   id = "epa_panelizer_node",
 * )
 */
class EpaPanelizerNode extends Node {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Get the default Node query.
    $query = parent::query();

    // Limit results to nodes that use a layout other than onecol_page or
    // twocol_page because these are the only nodes that will need special
    // processing and migration into Layout Builder.
    $query->innerJoin('panelizer_entity', 'pe', 'n.vid = pe.revision_id');
    $query->innerJoin('panels_display', 'pd', 'pe.did = pd.did');
    $query->condition('pe.did', 0, '<>');
    $query->condition('pd.layout', 'onecol_page', '<>');
    $query->condition('pd.layout', 'twocol_page', '<>');

    // Add the 'did' field from panelizer_entity so we can access it during the
    // process phase.
    $query->fields('pe', ['did']);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = parent::fields();
    $fields['did'] = $this->t('Panelizer Display ID');

    return $fields;
  }

}
