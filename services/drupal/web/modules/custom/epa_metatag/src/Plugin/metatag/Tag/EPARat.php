<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.Subject.eparat' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_rat",
 *   label = @Translation("Research, Analysis & Technology Topics"),
 *   description = @Translation("EPA Research, Analysis & Technology Topics."),
 *   name = "DC.Subject.eparat",
 *   group = "epa",
 *   weight = 12,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPARat extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
