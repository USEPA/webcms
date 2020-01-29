<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.Subject.epachannel' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_channel",
 *   label = @Translation("Channel"),
 *   description = @Translation("EPA channel."),
 *   name = "DC.Subject.epachannel",
 *   group = "epa",
 *   weight = 2,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPAChannel extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
