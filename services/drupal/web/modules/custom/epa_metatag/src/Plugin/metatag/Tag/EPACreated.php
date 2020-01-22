<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.date.created' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_created",
 *   label = @Translation("Date Created"),
 *   description = @Translation("Date of content creation."),
 *   name = "DC.date.created",
 *   group = "epa",
 *   weight = 18,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPACreated extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
