<?php

namespace Drupal\epa_viewsreference\Plugin\ViewsReferenceSetting;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\ViewExecutable;
use Drupal\viewsreference\Plugin\ViewsReferenceSettingInterface;
use Drupal\viewsreference_filter\ViewsRefFilterUtilityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The views reference setting argument plugin.
 *
 * @ViewsReferenceSetting(
 *   id = "epa_view_mode",
 *   label = @Translation("View Mode"),
 *   default_value = "",
 * )
 */
class ViewsReferenceViewMode extends PluginBase implements ViewsReferenceSettingInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The factory to load a view executable with.
   *
   * @var \Drupal\views\ViewExecutableFactory
   */
  protected $viewsUtility;

  /**
   * Constructor.
   *
   * @param array $configuration
   * @param $pluginId
   * @param $pluginDefinition
   */
  public function __construct(array $configuration,
                              $pluginId, $pluginDefinition, ViewsRefFilterUtilityInterface $viewsUtility) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->viewsUtility = $viewsUtility;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('viewsreference_filter.views_utility')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function alterFormField(array &$form_field) {
    // List of view modes that we actually want selectable by our users
    $view_mode_whitelist = ['link'=> 'Link', 'teaser'=>'Teaser'];

    $view = $this->viewsUtility->loadView($this->configuration['view_name'],
      $this->configuration['display_id']);
    if (!$view || !$view->getBaseEntityType() || empty($view->display_handler->getPlugin('row')->options['view_mode'])) {
      $form_field = [];
      return;
    }

    $view_mode_options = \Drupal::service('entity_display.repository')->getViewModeOptions($view->getBaseEntityType()->id());
    $view_mode_options = array_intersect_key($view_mode_options, $view_mode_whitelist);
    $form_field['#type'] = 'select';
    $form_field['#options'] = $view_mode_options;

    $form_field['#weight'] = 70;
  }

  /**
   * {@inheritdoc}
   */
  public function alterView(ViewExecutable $view, $value) {
    $row_plugin = $view->display_handler->getPlugin('row');
    if ($row_plugin->options['view_mode']) {
      $row_plugin->options['view_mode'] = $value;
    }
  }

}
