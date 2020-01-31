<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.title' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_title",
 *   label = @Translation("Title"),
 *   description = @Translation("The title."),
 *   name = "DC.title",
 *   group = "epa",
 *   weight = 0,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPATitle extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
