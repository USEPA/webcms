<?php

namespace Drupal\epa_core\Utility;

use Drupal\pathauto\AliasCleanerInterface;

/**
 * Class EpaCoreHelper.
 */
class EpaCoreHelper {

  /**
   * The alias cleaner interface.
   *
   * @var \Drupal|pathauto\AliasCleanerInterface
   */
  protected $aliasCleaner;

  /**
   * Constructs a new WebAreasHelper.
   *
   * @param \Drupal\pathauto\AliasCleanerInterface $alias_cleaner
   *   The alias cleaner interface.
   */
  public function __construct(AliasCleanerInterface $alias_cleaner) {
    $this->aliasCleaner = $alias_cleaner;
  }

  /**
   * Helper function to determine node machine name.
   */
  public function getEntityMachineNameAlias($entity) {
    $alias_string = '';

    // Set alias_string to machine name.
    // If $alias_string is empty fallback to node's title.
    if (!$entity->get('field_machine_name')->isEmpty()) {
      $alias_string = $entity->get('field_machine_name')->value;
    }
    if (empty($alias_string)) {
      $alias_string = $this->aliasCleaner->cleanString($entity->label());
    }

    return $alias_string;
  }

}
