<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.audience' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_audience",
 *   label = @Translation("Audiences"),
 *   description = @Translation("EPA Audiences."),
 *   name = "DC.audience",
 *   group = "epa",
 *   weight = 16,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPAAudience extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
