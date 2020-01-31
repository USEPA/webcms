<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.date.modified' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_modified",
 *   label = @Translation("Date Modified"),
 *   description = @Translation("Date content last modified."),
 *   name = "DC.date.modified",
 *   group = "epa",
 *   weight = 19,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPAModified extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
