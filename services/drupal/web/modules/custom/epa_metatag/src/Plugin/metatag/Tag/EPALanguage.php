<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.language' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_language",
 *   label = @Translation("Content language"),
 *   description = @Translation("Content language."),
 *   name = "DC.language",
 *   group = "epa",
 *   weight = 22,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPALanguage extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
