<?php

namespace Drupal\epa_web_areas\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\entitygroupfield\Plugin\Field\FieldFormatter\ParentGroupFormatterTrait;

/**
 * Provides access to the label of a homepage node for a Web Area group when
 * referenved via a group_content entity.
 *
 * @FieldFormatter(
 *   id = "group_homepage_node_formatter",
 *   label = @Translation("EPA Group Homepage Node label"),
 *   description = @Translation("Display the label of the Group's homepage node."),
 *   field_types = {
 *     "group_content"
 *   }
 * )
 */
class EpaGroupHomepageNodeFormatter extends EntityReferenceLabelFormatter {

  use ParentGroupFormatterTrait {
    getEntitiesToView as protected traitGetEntitiesToView;
  }
  /**
   * {@inheritdoc}
   */
  protected function getEntitiesToView(EntityReferenceFieldItemListInterface $items, $langcode) {
    $groups = $this->traitGetEntitiesToView($items,$langcode);
    $homepages = [];

    foreach ($groups as $delta => $group) {
      $homepage = $group->field_homepage->entity;

      // Set the node in the correct language for display.
      if ($homepage instanceof TranslatableInterface) {
        $homepage = \Drupal::entityManager()->getTranslationFromContext($homepage, $langcode);
      }

      $access = $this->checkAccess($homepage);
      // Add the access result's cacheability, ::view() needs it.
      $item = $group->_referringItem;
      $item->_accessCacheability = CacheableMetadata::createFromObject($access);

      if ($access->isAllowed()) {
        // Add the referring item, in case the formatter needs it.
        $homepage->_referringItem = $items[$delta];
        $homepages[$delta] = $homepage;
      }
    }

    return $homepages;
  }

}
