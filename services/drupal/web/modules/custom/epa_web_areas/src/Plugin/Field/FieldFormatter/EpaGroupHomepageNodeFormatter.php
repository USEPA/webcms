<?php

namespace Drupal\epa_web_areas\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\entitygroupfield\Plugin\Field\FieldFormatter\ParentGroupFormatterTrait;

/**
 * Provides access to the label of a homepage node for a Web Area group when
 * referenced via a group_content entity.
 *
 * @FieldFormatter(
 *   id = "group_homepage_node_formatter",
 *   label = @Translation("EPA Group Homepage Node label"),
 *   description = @Translation("Display Group's homepage node, link to it if desired."),
 *   field_types = {
 *     "entitygroupfield"
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
  public static function defaultSettings() {
    return [
        'shortname' => FALSE,
      ] + parent::defaultSettings();
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
        $homepage = \Drupal::service('entity.repository')->getTranslationFromContext($homepage, $langcode);
      }

      $access = $this->checkAccess($homepage);
      // Add the access result's cacheability, ::view() needs it.
      $item = $group->_referringItem;
      $item->_accessCacheability = CacheableMetadata::createFromObject($access);

      if ($access->isAllowed()) {
        // Add the referring item, in case the formatter needs it.
        $homepage->_referringItem = $items[$delta];
        $homepage->_referringGroup = $group;
        $homepages[$delta] = $homepage;
      }
    }

    return $homepages;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $output_as_link = $this->getSetting('link');
    $shortname = $this->getSetting('shortname');

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      $label = $entity->label();

      if ($shortname) {
        $label = $entity->_referringGroup->label();
      }
      // If the link is to be displayed and the entity has a uri, display a
      // link.
      if ($output_as_link && !$entity->isNew()) {
        try {
          $uri = $entity->toUrl();
        }
        catch (UndefinedLinkTemplateException $e) {
          // This exception is thrown by \Drupal\Core\Entity\Entity::urlInfo()
          // and it means that the entity type doesn't have a link template nor
          // a valid "uri_callback", so don't bother trying to output a link for
          // the rest of the referenced entities.
          $output_as_link = FALSE;
        }
      }

      if ($output_as_link && isset($uri) && !$entity->isNew()) {
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
      else {
        $elements[$delta] = ['#plain_text' => $label];
      }
      $elements[$delta]['#cache']['tags'] = $entity->getCacheTags();
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $elements['shortname'] = [
      '#title' => t('Use short name'),
      '#description' => t('Display the short name for the group (the group entity title) rather than the group homepage name'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('shortname'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = $this->getSetting('shortname') ? t('Display short name') : t('Display long name');
    return $summary;
  }
}
