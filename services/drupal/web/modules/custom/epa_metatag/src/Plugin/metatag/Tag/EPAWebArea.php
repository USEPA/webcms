<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'WebArea' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_webarea",
 *   label = @Translation("Web Area"),
 *   description = @Translation("The web area to which the content belongs."),
 *   name = "WebArea",
 *   group = "epa",
 *   weight = 24,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPAWebArea extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
