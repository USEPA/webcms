<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.description' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_description",
 *   label = @Translation("Description"),
 *   description = @Translation("EPA description."),
 *   name = "DC.description",
 *   group = "epa",
 *   weight = 1,
 *   type = "text",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPADescription extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
