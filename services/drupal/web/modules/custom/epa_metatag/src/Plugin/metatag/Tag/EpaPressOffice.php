<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The Open Graph "PressOffice" meta tag.
 *
 * @MetatagTag(
 *   id = "epa_pressoffice",
 *   label = @Translation("Press Office(s)"),
 *   description = @Translation("EPA Press Office(s)"),
 *   name = "PressOffice",
 *   group = "epa_custom",
 *   weight = 26,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EpaPressOffice extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
