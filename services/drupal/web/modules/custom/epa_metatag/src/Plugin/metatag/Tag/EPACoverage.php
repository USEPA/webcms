<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.coverage' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_coverage",
 *   label = @Translation("Geographic Locations"),
 *   description = @Translation("EPA Geographic Locations."),
 *   name = "DC.coverage",
 *   group = "epa",
 *   weight = 17,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPACoverage extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
