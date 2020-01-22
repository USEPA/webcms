<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.Subject.epaemt' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_emt",
 *   label = @Translation("Environmental Media Topics"),
 *   description = @Translation("EPA Environmental Media Topics."),
 *   name = "DC.Subject.epaemt",
 *   group = "epa",
 *   weight = 7,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPAEmt extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
