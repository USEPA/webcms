<?php

namespace Drupal\epa_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'DC.date.reviewed' meta tag.
 *
 * @MetatagTag(
 *   id = "epa_reviewed",
 *   label = @Translation("Date Reviewed"),
 *   description = @Translation("Date content last reviewed."),
 *   name = "DC.date.reviewed",
 *   group = "epa",
 *   weight = 20,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class EPAReviewed extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
