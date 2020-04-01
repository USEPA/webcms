<?php

namespace Drupal\epa_wysiwyg;

use Drupal\filter\Plugin\Filter\FilterHtml;

class EpaFilterHtml extends FilterHtml {
  /**
   * {@inheritdoc}
   */
  public function getHTMLRestrictions() {
    if ($this->restrictions) {
      return $this->restrictions;
    }

    $restrictions = parent::getHTMLRestrictions();
    $star_protector = '__zqh6vxfbk3cg__';
    if (isset($restrictions['allowed'][$star_protector])) {
      $restrictions['allowed']['*'] = array_merge($restrictions['allowed']['*'], $restrictions['allowed'][$star_protector]);
    }

    $this->restrictions = $restrictions;

    return $this->restrictions;
  }
}
