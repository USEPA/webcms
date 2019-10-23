<?php

namespace Drupal\epa_web_areas\Entity\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\Form\GroupForm;

/**
 * Extends form controller for the group add and edit forms.
 *
 * Removes access to field_homepage and creates
 * pseudo-field linking to node if assigned.
 */
class EPAGroupForm extends GroupForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    // For web area type.
    if ($this->entity->bundle() == 'web_area' && isset($form['field_homepage'])) {
      $form['field_homepage']['#access'] = FALSE;
      $homepage = $form['field_homepage']['widget'][0]['target_id']['#default_value'];

      // If homepage is associated with web area
      // provide a link to the homepage.
      if (!empty($homepage)) {
        $url = $homepage->toUrl('edit-form');
        $markup = $this->t('<a href=":url" target="_blank">@title</a>',
          [
            '@title' => $homepage->getTitle(),
            ':url' => $url->toString(),
          ]
        );

        $form['field_homepage_link'] = [
          '#type' => 'item',
          '#title' => $form['field_homepage']['widget']['#title'],
          '#markup' => $markup,
          '#weight' => $form['field_homepage']['#weight'],
        ];
      }
    }
    return $form;
  }

}
