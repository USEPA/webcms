<?php

namespace Drupal\epa_viewsreference\Plugin\ViewsReferenceSetting;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\ViewExecutable;
use Drupal\viewsreference\Plugin\ViewsReferenceSettingInterface;

/**
 * The views reference setting pager plugin.
 *
 * @ViewsReferenceSetting(
 *   id = "no_results_text",
 *   label = @Translation("EPA Custom No Results text"),
 *   default_value = "",
 * )
 */
class ViewsReferenceNoResultsText extends PluginBase implements ViewsReferenceSettingInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function alterFormField(array &$form_field) {
    $form_field['#weight'] = 70;
    $form_field['#type'] = 'details';
    $form_field['#open'] = TRUE;
    $form_field['#title'] = $this->t('No Results Text');
    $form_field['#tree'] = TRUE;

    $current_values = $form_field['#default_value'];
    unset($form_field['#default_value']);

    $form_field['no_results_text'] = [
      '#type' => 'text_format',
      '#format' => 'restricted_html',
      '#allowed_formats' => ['restricted_html'],
      '#default_value' => $current_values['no_results_text']['value'] ?? NULL,
      '#description' => $this->t('If supplied, this text will overwrite the default no results text that is shown.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function alterView(ViewExecutable $view, $value) {
    if (empty($value['no_results_text']['value'])) {
      return;
    }

    // Check the view for existing empty display handler.
    $empty = $view->display_handler->getHandlers('empty');
    if (!empty($empty)) {
      $empty = reset($empty);
      $empty->options['content']['value'] = $value['no_results_text']['value'];
      $view->setHandler($view->current_display, 'empty', $empty->pluginDefinition['id'], $empty->options);
    }

  }

}
