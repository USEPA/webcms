<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'ContentType' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_contenttype",
 *   label = @Translation("Content Type"),
 *   description = @Translation("The type of content."),
 *   name = "ContentType",
 *   group = "epa",
 *   weight = 25,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPAContentType extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
