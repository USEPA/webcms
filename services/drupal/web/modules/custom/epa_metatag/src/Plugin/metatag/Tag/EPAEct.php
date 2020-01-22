<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.Subject.epaect' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_ect",
 *   label = @Translation("Emergencies & Cleanup Topics"),
 *   description = @Translation("EPA Emergencies & Cleanup Topics."),
 *   name = "DC.Subject.epaect",
 *   group = "epa",
 *   weight = 6,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPAEct extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
