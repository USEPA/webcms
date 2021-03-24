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
 *   id = "epa_pager",
 *   label = @Translation("EPA Pagination Toggle"),
 *   default_value = 0,
 * )
 */
class ViewsReferenceEpaPager extends PluginBase implements ViewsReferenceSettingInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function alterFormField(array &$form_field) {
    $form_field['#type'] = 'checkbox';
    $form_field['#title'] = $this->t('Display pagination');
    $form_field['#weight'] = 35;
  }

  /**
   * {@inheritdoc}
   */
  public function alterView(ViewExecutable $view, $value) {
    $pager = $view->display_handler->getOption('pager');
    $pager['type'] = $value ? 'full' : 'some';
    $view->display_handler->setOption('pager', $pager);
  }

}
