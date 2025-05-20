<?php

namespace Drupal\media_inline_embed\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5Plugin\MediaLibrary;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\editor\EditorInterface;
use Drupal\media\Entity\MediaType;
use Drupal\media_library\MediaLibraryState;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DrupalInlineMedia extends MediaLibrary implements CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Media constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   */
  public function __construct(array $configuration, string $plugin_id, CKEditor5PluginDefinition $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepositoryInterface $entity_display_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['allow_view_mode_override' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['allow_view_mode_override'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow the user to override the default view mode'),
      '#default_value' => $this->configuration['allow_view_mode_override'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $form_value = $form_state->getValue('allow_view_mode_override');
    $form_state->setValue('allow_view_mode_override', (bool) $form_value);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['allow_view_mode_override'] = $form_state->getValue('allow_view_mode_override');
  }


  /**
   * Configures allowed view modes.
   *
   * @param \Drupal\editor\EditorInterface $editor
   *   A configured text editor object.
   *
   * @return array
   *   An array containing view modes, style configuration,
   *   and toolbar configuration.
   */
  private function configureViewModes(EditorInterface $editor) {
    $element_style_configuration = [];
    $toolbar_configuration = [];

    $media_embed_filter = $editor->getFilterFormat()->filters('media_inline_embed');
    $media_bundles = MediaType::loadMultiple();
    $bundles_per_view_mode = [];
    $all_view_modes = $this->entityDisplayRepository->getViewModeOptions('media');
    $allowed_view_modes = $media_embed_filter->settings['allowed_view_modes'];
    $default_view_mode = $media_embed_filter->settings['default_view_mode'];
    // @todo Remove in https://www.drupal.org/project/drupal/issues/3277049.
    // This is a workaround until the above issue is fixed to prevent the
    // editor from crashing because the frontend expects the default view mode
    // to exist in drupalElementStyles.
    if (!array_key_exists($default_view_mode, $allowed_view_modes)) {
      $allowed_view_modes[$default_view_mode] = $default_view_mode;
    }
    // Return early since there is no need to configure if there
    // are less than 2 view modes.
    if ($allowed_view_modes < 2) {
      return [];
    }

    // Configure view modes.
    foreach (array_keys($media_bundles) as $bundle) {
      $allowed_view_modes_by_bundle = $this->entityDisplayRepository->getViewModeOptionsByBundle('media', $bundle);

      foreach (array_keys($allowed_view_modes_by_bundle) as $view_mode) {
        // Get the bundles that have this view mode enabled.
        $bundles_per_view_mode[$view_mode][] = $bundle;
      }
    }
    // Limit to view modes allowed by filter.
    $bundles_per_view_mode = array_intersect_key($bundles_per_view_mode, $allowed_view_modes);

    // Configure view mode element styles.
    foreach (array_keys($all_view_modes) as $view_mode) {
      if (array_key_exists($view_mode, $bundles_per_view_mode)) {
        $specific_bundles = $bundles_per_view_mode[$view_mode];
        if ($view_mode == $default_view_mode) {
          $element_style_configuration[] = [
            'isDefault' => TRUE,
            'name' => $default_view_mode,
            'title' => $all_view_modes[$view_mode],
            'attributeName' => 'data-view-mode',
            'attributeValue' => $view_mode,
            'modelElements' => ['drupalInlineMedia'],
            'modelAttributes' => [
              'drupalMediaType' => array_keys($media_bundles),
            ],
          ];
        }
        else {
          $element_style_configuration[] = [
            'name' => $view_mode,
            'title' => $all_view_modes[$view_mode],
            'attributeName' => 'data-view-mode',
            'attributeValue' => $view_mode,
            'modelElements' => ['drupalInlineMedia'],
            'modelAttributes' => [
              'drupalMediaType' => $specific_bundles,
            ],
          ];
        }
      }
    }

    $items = [];

    foreach (array_keys($allowed_view_modes) as $view_mode) {
      $items[] = "drupalInlineElementStyle:viewMode:$view_mode";
    }

    $default_item = 'drupalInlineElementStyle:viewMode:' . $default_view_mode;
    if (!empty($allowed_view_modes)) {
      // Configure toolbar dropdown menu.
      // @TODO: Need to 'register' our own element for this otherwise we clobber the drupalMedia and drupalInlineMedia together
      $toolbar_configuration = [
        'name' => 'drupalInlineMedia:viewMode',
        'display' => 'listDropdown',
        'defaultItem' => $default_item,
        'defaultText' => 'View mode',
        'items' => $items,
      ];
    }
    return [
      $element_style_configuration,
      $toolbar_configuration,
    ];
  }

  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    // @todo Validate we still need this.
    // If the editor has not been saved yet, we may not be able to create a
    // coherent MediaLibraryState object, which is needed in order to generate
    // the required configuration. But, if we're creating a new editor, we don't
    // need to do that anyway, so just return an empty array.
    if ($editor->isNew()) {
      return [];
    }

    $media_type_ids = $this->mediaTypeStorage->getQuery()->execute();
    if ($editor->hasAssociatedFilterFormat()) {
      if ($media_embed_filter = $editor->getFilterFormat()->filters()->get('media_inline_embed')) {
        // Optionally limit the allowed media types based on the MediaEmbed
        // setting. If the setting is empty, do not limit the options.
        if (!empty($media_embed_filter->settings['allowed_media_types'])) {
          $media_type_ids = array_intersect_key($media_type_ids, $media_embed_filter->settings['allowed_media_types']);
        }
      }
    }

    if (in_array('image', $media_type_ids, TRUE)) {
      // Move image to first position.
      // This workaround can be removed once this issue is fixed:
      // @see https://www.drupal.org/project/drupal/issues/3073799
      array_unshift($media_type_ids, 'image');
      $media_type_ids = array_unique($media_type_ids);
    }

    $state = MediaLibraryState::create(
      'media_inline_embed.opener.editor',
      $media_type_ids,
      reset($media_type_ids),
      1,
      ['filter_format_id' => $editor->getFilterFormat()->id()]
    );

    $library_url = Url::fromRoute('media_library.ui')
      ->setOption('query', $state->all())
      ->toString(TRUE)
      ->getGeneratedUrl();

    $dynamic_plugin_config = $static_plugin_config;
    $dynamic_plugin_config['drupalInlineMedia']['libraryURL'] = $library_url;

    [$element_style_configuration, $toolbar_configuration,
    ] = self::configureViewModes($editor);

    $dynamic_plugin_config['drupalInlineElementStyles']['viewMode'] = $element_style_configuration;
    if ($this->getConfiguration()['allow_view_mode_override']) {
      $dynamic_plugin_config['drupalInlineMedia']['toolbar'][] = $toolbar_configuration;
    }
    return $dynamic_plugin_config;

  }

}
