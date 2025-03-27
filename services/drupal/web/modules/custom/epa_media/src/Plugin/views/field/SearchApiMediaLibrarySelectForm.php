<?php

namespace Drupal\epa_media\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\media_library\Plugin\views\field\MediaLibrarySelectForm;
use Drupal\search_api\Plugin\views\field\SearchApiFieldTrait;
use Drupal\views\ResultRow;

/**
 * Defines a field that outputs a checkbox and form for selecting media.
 *
 * @ViewsField("search_api_media_library_select_form")
 *
 * @internal
 *   Plugin classes are internal.
 */
class SearchApiMediaLibrarySelectForm extends MediaLibrarySelectForm {
  use SearchApiFieldTrait;

  public function form_element_row_id(int $row_id): string {
    return $this->getEntity($this->view->result[$row_id])->mid->value;
  }

  public function getValue(ResultRow $row, $field = NULL) {
    $row->mid = $this->form_element_row_id($row->index);
    return parent::getValue($row, $field);
  }

  /**
   * Form constructor for the media library select form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function viewsForm(array &$form, FormStateInterface $form_state) {
    foreach ($this->view->result as $row_index => $row) {
      $this->view->result[$row_index]->mid = $this->form_element_row_id($row_index);
    }
    parent::viewsForm($form, $form_state);
  }
}
