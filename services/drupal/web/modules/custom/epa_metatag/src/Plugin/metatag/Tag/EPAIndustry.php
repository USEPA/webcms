<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.Subject.epaindustry' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_industry",
 *   label = @Translation("Industries"),
 *   description = @Translation("EPA Industries."),
 *   name = "DC.Subject.epaindustry",
 *   group = "epa",
 *   weight = 9,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPAIndustry extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
