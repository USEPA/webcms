<?php

namespace Drupal\epa_metatag\Plugin\metatag\Group;

use Drupal\metatag\Plugin\metatag\Group\GroupBase;

/**
 * The EPA metatag group.
 *
 * @MetatagGroup(
 *   id = "epa",
 *   label = @Translation("EPA: DublinCore"),
 *   description = @Translation("EPA-specific metatags."),
 *   weight = 0
 * )
 */
class EPA extends GroupBase {
  // Inherits everything from Base.
}
