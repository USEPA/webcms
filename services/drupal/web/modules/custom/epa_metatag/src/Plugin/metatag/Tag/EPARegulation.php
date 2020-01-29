<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.Subject.eparegulation' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_regulation",
 *   label = @Translation("Environmental Laws, Regulations & Treaties"),
 *   description = @Translation("EPA Environmental Laws, Regulations & Treaties."),
 *   name = "DC.Subject.eparegulation",
 *   group = "epa",
 *   weight = 13,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPARegulation extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
