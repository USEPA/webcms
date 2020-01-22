<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.Subject.epacat' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_cat",
 *   label = @Translation("Cooperation & Assistance Topics"),
 *   description = @Translation("EPA Cooperation & Assistance Topics."),
 *   name = "DC.Subject.epacat",
 *   group = "epa",
 *   weight = 5,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPACat extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
