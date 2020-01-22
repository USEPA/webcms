<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.Subject.epaopt' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_opt",
 *   label = @Translation("Operations Topics"),
 *   description = @Translation("EPA Operations Topics."),
 *   name = "DC.Subject.epaopt",
 *   group = "epa",
 *   weight = 10,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPAOpt extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
