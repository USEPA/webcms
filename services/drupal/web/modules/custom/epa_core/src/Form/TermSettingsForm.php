<?php

namespace Drupal\epa_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for Term Description settings.
 */
class TermSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'term_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['epa_core.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('epa_core.settings');
    $form['default_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Term Default Description'),
      '#default_value' => $config->get('default_description'),
      '#description' => $this->t('Default description for taxonomy terms.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('epa_core.settings');
    $config->set('default_description', $form_state->getValue('default_description'));
    $config->save();

    parent::submitForm($form, $form_state);

  }

}
