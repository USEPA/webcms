<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.Subject.eparit' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_rit",
 *   label = @Translation("Regulatory & Industrial Topics"),
 *   description = @Translation("EPA Regulatory & Industrial Topics."),
 *   name = "DC.Subject.eparit",
 *   group = "epa",
 *   weight = 14,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPARit extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
