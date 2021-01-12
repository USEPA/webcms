<?php

namespace Drupal\epa_web_areas\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;

/**
 * Provides a link to the homepage node for a Web Area group when referenced via
 * normal entity reference field.
 *
 * @FieldFormatter(
 *   id = "web_areas_homepage_link_formatter",
 *   label = @Translation("EPA Web Area Homepage Link"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EpaWebAreaHomepageLinkFormatter extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // This formatter is only available for taxonomy terms.
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'group';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      // Don't attempt to output a link if the referenced entity isn't a group.
      if ($entity->bundle() !== 'web_area') {
        return $elements;
      }

      $homepage = $entity->field_homepage->entity;
      $label = $entity->label();
      if (empty($homepage)) {
        return $elements;
      }
      // If the link is to be displayed and the entity has a uri, display a
      // link.
      if (!$homepage->isNew()) {
        try {
          $uri = $homepage->toUrl();
        }
        catch (UndefinedLinkTemplateException $e) {
          // This exception is thrown by \Drupal\Core\Entity\Entity::urlInfo()
          // and it means that the entity type doesn't have a link template
          // nor a valid "uri_callback", so don't bother trying to output a
          // link.
          return $elements;
        }
      }

      if (isset($uri) && !$homepage->isNew()) {
        $elements[$delta] = [
          '#type' => 'link',
          '#title' => $label,
          '#url' => $uri,
          '#options' => $uri->getOptions(),
        ];

        if (!empty($items[$delta]->_attributes)) {
          $elements[$delta]['#options'] += ['attributes' => []];
          $elements[$delta]['#options']['attributes'] += $items[$delta]->_attributes;
          // Unset field item attributes since they have been included in the
          // formatter output and shouldn't be rendered in the field template.
          unset($items[$delta]->_attributes);
        }
      }
      $elements[$delta]['#cache']['tags'] = array_merge($entity->getCacheTags(), $homepage->getCacheTags());
    }

    return $elements;
  }

}
