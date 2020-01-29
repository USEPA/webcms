<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.creator' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_creator",
 *   label = @Translation("Content Creator"),
 *   description = @Translation("EPA Content Creator."),
 *   name = "DC.creator",
 *   group = "epa",
 *   weight = 21,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPACreator extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
