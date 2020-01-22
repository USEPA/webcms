<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'WebAreaType' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_webareatype",
 *   label = @Translation("Web Area Type"),
 *   description = @Translation("The type of web area to which the content belongs."),
 *   name = "WebAreaType",
 *   group = "epa",
 *   weight = 26,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPAWebAreaType extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
