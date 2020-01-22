<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.Subject.epasubstance'' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_substance",
 *   label = @Translation("Substances"),
 *   description = @Translation("EPA Substances."),
 *   name = "DC.Subject.epasubstance",
 *   group = "epa",
 *   weight = 15,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPASubstance extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
