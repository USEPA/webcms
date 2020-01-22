<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.Subject.epappt' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_ppt",
 *   label = @Translation("Pollution Prevention Topics"),
 *   description = @Translation("EPA Pollution Prevention Topics."),
 *   name = "DC.Subject.epappt",
 *   group = "epa",
 *   weight = 11,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPAPPt extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
