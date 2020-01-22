<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.Subject.epahealth' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_health",
 *   label = @Translation("Health Topics"),
 *   description = @Translation("EPA Health Topics."),
 *   name = "DC.Subject.epahealth",
 *   group = "epa",
 *   weight = 8,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPAHealth extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
