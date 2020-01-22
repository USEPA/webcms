<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.Subject.epabrm' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_agency_function",
 *   label = @Translation("Agency Function"),
 *   description = @Translation("EPA Agency Function."),
 *   name = "DC.Subject.epabrm",
 *   group = "epa",
 *   weight = 4,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPAAgencyFunction extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
