<?php

namespace Drupal\epa_metatag\Plugin\metatag\Group;

use Drupal\metatag\Plugin\metatag\Group\GroupBase;

/**
 * The EPA custom metatag group.
 *
 * @MetatagGroup(
 *   id = "epa_custom",
 *   label = @Translation("EPA: Custom"),
 *   description = @Translation("EPA-specific custom metatags."),
 *   weight = 0
 * )
 */
class EpaCustom extends GroupBase {
  // Inherits everything from Base.
}
