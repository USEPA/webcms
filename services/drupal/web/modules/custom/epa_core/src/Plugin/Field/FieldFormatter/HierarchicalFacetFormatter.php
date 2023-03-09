<?php

namespace Drupal\epa_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hierarchical_term_formatter\Plugin\Field\FieldFormatter\HierarchicalFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'HierarchicalFacetFormatter' formatter.
 *
 * @FieldFormatter(
 *   id = "epa_core_hierarchical_term_facet_formatter",
 *   label = @Translation("Hierarchical Term Facet Formatter"),
 *   description = @Translation("Provides hierarchical term formatters for
 *   taxonomy reference fields to related Facet sources."), field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class HierarchicalFacetFormatter extends HierarchicalFormatter {

  /**
   * Facet entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $facetStorage;

  /**
   * Constructs a HierarchicalFacetFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Entity\EntityStorageInterface $taxonomy_term_storage
   *   The Taxonomy Term storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $facet_storage
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityStorageInterface $taxonomy_term_storage, EntityStorageInterface $facet_storage) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $taxonomy_term_storage);
    $this->facetStorage = $facet_storage;
  }

  /**
   *
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')->getStorage('taxonomy_term'),
      $container->get('entity_type.manager')->getStorage('facets_facet')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'facet_source' => "",
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['link']['#description'] = $this->t('If checked, the terms will link to their corresponding facet page.');

    $form['facet_source'] = [
      '#type' => 'select',
      '#options' => $this->facetOptions(),
      '#title' => $this->t('Facet Source'),
      '#default_value' => $this->getSetting('facet_source'),
    ];

    return $form;
  }

  /**
   * Generates options of available facets for this particular field.
   *
   * @return array
   */
  private function facetOptions() {
    $options = [];

    /** @var \Drupal\facets\FacetInterface $facet */
    foreach ($this->facetStorage->loadMultiple() as $facet) {
      // Only allow choosing facets that apply to this field.
      if ($facet->getFieldIdentifier() == $this->fieldDefinition->getName()) {
        $options[$facet->id()] = $facet->label() . " ({$facet->id()})";
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Facet: @facet_source', ['@facet_source' => $this->getSetting('facet_source')]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    foreach ($elements as $delta => $element) {
      $elements[$delta]['#theme'] = 'hierarchical_term_facet_formatter';
      $elements[$delta]['#facet_source'] = $this->getSetting('facet_source');
    }

    return $elements;
  }

}
