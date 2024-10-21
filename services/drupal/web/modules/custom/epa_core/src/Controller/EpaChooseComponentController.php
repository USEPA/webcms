<?php

namespace Drupal\epa_core\Controller;

use Drupal\layout_paragraphs\Controller\ChooseComponentController;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Symfony\Component\HttpFoundation\Request;

class EpaChooseComponentController extends ChooseComponentController {
  public function list(Request $request, LayoutParagraphsLayout $layout_paragraphs_layout) {
    $route_params = [
      'layout_paragraphs_layout' => $layout_paragraphs_layout->id(),
    ];
    $context = $this->getContextFromRequest($request);
    // If inserting a new item adjecent to a sibling component, the region
    // passed in the URL will be incorrect if the existing sibling component
    // was dragged into another region. In that case, always use the existing
    // sibling's region.
    if ($context['sibling_uuid']) {
      $sibling = $layout_paragraphs_layout->getComponentByUuid($context['sibling_uuid']);
      $context['region'] = $sibling->getRegion();
    }
    $types = $this->getAllowedComponentTypes($layout_paragraphs_layout, $context);

    // Per https://forumone.atlassian.net/browse/EPAD8-2552 we need to remove
    // 'Link List' as an available option. However, we can't simply remove it
    // from the list as there are pages that are still using the component which
    // if we remove access, will prevent the user from saving the page.
    if (isset($types['link_list'])) {
      unset($types['link_list']);
    }

    // If there is only one type to render,
    // return the component form instead of a list of links.
    if (count($types) === 1) {
      return $this->componentForm(key($types), $layout_paragraphs_layout, $context);
    }
    else {
      return $this->componentMenu($types, $route_params, $context);
    }
  }

}
