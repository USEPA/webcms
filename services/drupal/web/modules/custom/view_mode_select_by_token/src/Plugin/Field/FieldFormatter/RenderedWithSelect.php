<?php

namespace Drupal\view_mode_select_by_token\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_browser\Plugin\EntityBrowser\FieldWidgetDisplay\RenderedEntity;


/**
 * Plugin implementation of the 'Render entity to Markdown' formatter.
 *
 * @FieldFormatter(
 *   id = "view_mode_select_by_token_rendered_with_select",
 *   label = @Translation("Rendered With Select"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class RenderedWithSelect extends EntityReferenceEntityFormatter {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['view_mode'] = [
      '#title' => $this->t('View Mode'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('view_mode'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
//    $this->viewMode = \Drupal::token()->replace($this->getSetting('view_mode'));
    // @Todo dependency inject this
    $entity = $items->getEntity();
    $entity_type = $entity->getEntityTypeId();
    $view_mode = \Drupal::token()->replace($this->getSetting('view_mode'), [$entity_type => $entity]);
    $elements = [];

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      // Due to render caching and delayed calls, the viewElements() method
      // will be called later in the rendering process through a '#pre_render'
      // callback, so we need to generate a counter that takes into account
      // all the relevant information about this field and the referenced
      // entity that is being rendered.
      $recursive_render_id = $items->getFieldDefinition()->getTargetEntityTypeId()
        . $items->getFieldDefinition()->getTargetBundle()
        . $items->getName()
        // We include the referencing entity, so we can render default images
        // without hitting recursive protections.
        . $items->getEntity()->id()
        . $entity->getEntityTypeId()
        . $entity->id();

      if (isset(static::$recursiveRenderDepth[$recursive_render_id])) {
        static::$recursiveRenderDepth[$recursive_render_id]++;
      }
      else {
        static::$recursiveRenderDepth[$recursive_render_id] = 1;
      }

      // Protect ourselves from recursive rendering.
      if (static::$recursiveRenderDepth[$recursive_render_id] > static::RECURSIVE_RENDER_LIMIT) {
        $this->loggerFactory->get('entity')->error('Recursive rendering detected when rendering entity %entity_type: %entity_id, using the %field_name field on the %parent_entity_type:%parent_bundle %parent_entity_id entity. Aborting rendering.', [
          '%entity_type' => $entity->getEntityTypeId(),
          '%entity_id' => $entity->id(),
          '%field_name' => $items->getName(),
          '%parent_entity_type' => $items->getFieldDefinition()->getTargetEntityTypeId(),
          '%parent_bundle' => $items->getFieldDefinition()->getTargetBundle(),
          '%parent_entity_id' => $items->getEntity()->id(),
        ]);
        return $elements;
      }

      $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
      $elements[$delta] = $view_builder->view($entity, $view_mode, $entity->language()->getId());

      // Add a resource attribute to set the mapping property's value to the
      // entity's url. Since we don't know what the markup of the entity will
      // be, we shouldn't rely on it for structured data such as RDFa.
      if (!empty($items[$delta]->_attributes) && !$entity->isNew() && $entity->hasLinkTemplate('canonical')) {
        $items[$delta]->_attributes += ['resource' => $entity->toUrl()->toString()];
      }
    }

    return $elements;
  }

}
