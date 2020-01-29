<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.type' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_type",
 *   label = @Translation("Type"),
 *   description = @Translation("EPA type."),
 *   name = "DC.type",
 *   group = "epa",
 *   weight = 3,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPAType extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
