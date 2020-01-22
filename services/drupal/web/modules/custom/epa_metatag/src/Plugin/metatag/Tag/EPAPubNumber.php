<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.epapubnumber' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_pubnumber",
 *   label = @Translation("Publication Number"),
 *   description = @Translation("EPA Publication Number."),
 *   name = "DC.epapubnumber",
 *   group = "epa",
 *   weight = 23,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPAPubNumber extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
