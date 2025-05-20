<?php

namespace Drupal\media_inline_embed\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\EditorInterface;
use Drupal\media\Form\EditorMediaDialog;

/**
 * Extends EditorMediaDialog.
 */
class EditorInlineMediaDialog extends EditorMediaDialog {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, EditorInterface $editor = NULL) {
    // This form is special, in that the default values do not come from the
    // server side, but from the client side, from a text editor. We must cache
    // this data in form state, because when the form is rebuilt, we will be
    // receiving values from the form, instead of the values from the text
    // editor. If we don't cache it, this data will be lost. By convention,
    // the data that the text editor sends to any dialog is in the
    // 'editor_object' key.
    if (isset($form_state->getUserInput()['editor_object'])) {
      $editor_object = $form_state->getUserInput()['editor_object'];
      // The data that the text editor sends to any dialog is in
      // the 'editor_object' key.
      // @see core/modules/ckeditor/js/ckeditor.es6.js
      $media_embed_element = $editor_object['attributes'];
      $form_state->set('media_embed_element', $media_embed_element);
      $has_caption = $editor_object['hasCaption'];
      $is_inline = empty($editor_object['isInline']) ? FALSE : TRUE;
      $form_state
        ->set('hasCaption', $has_caption)
        ->set('isInline', $is_inline)
        ->setCached(TRUE);
    }
    else {
      // Retrieve the user input from form state.
      $media_embed_element = $form_state->get('media_embed_element');
      $has_caption = $form_state->get('hasCaption');
      $is_inline = $form_state->get('isInline');
    }

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'editor/drupal.editor.dialog';
    $form['#prefix'] = '<div id="editor-media-dialog-form">';
    $form['#suffix'] = '</div>';

    $filters = $editor->getFilterFormat()->filters();
    $filter_html = $filters->get('filter_html');
    $filter_align = $filters->get('filter_align');
    $filter_caption = $filters->get('filter_caption');
    $media_embed_filter = $is_inline ? $filters->get('media_inline_embed') : $filters->get('media_embed');

    $allowed_attributes = [];
    if ($filter_html->status) {
      $restrictions = $filter_html->getHTMLRestrictions();
      $allowed_attributes = $is_inline ? $restrictions['allowed']['drupal-inline-media'] : $restrictions['allowed']['drupal-media'];
    }

    $media = $this->entityRepository->loadEntityByUuid('media', $media_embed_element['data-entity-uuid']);

    if ($image_field_name = $this->getMediaImageSourceFieldName($media)) {
      // We'll want the alt text from the same language as the host.
      if (!empty($editor_object['hostEntityLangcode']) && $media->hasTranslation($editor_object['hostEntityLangcode'])) {
        $media = $media->getTranslation($editor_object['hostEntityLangcode']);
      }
      $settings = $media->{$image_field_name}->getItemDefinition()->getSettings();
      $alt = isset($media_embed_element['alt']) ? $media_embed_element['alt'] : NULL;
      $form['alt'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Alternate text'),
        '#default_value' => $alt,
        '#description' => $this->t('Short description of the image used by screen readers and displayed when the image is not loaded. This is important for accessibility.'),
        '#required_error' => $this->t('Alternative text is required.<br />(Only in rare cases should this be left empty. To create empty alternative text, enter <code>""</code> — two double quotes without any content).'),
        '#maxlength' => 2048,
        '#placeholder' => $media->{$image_field_name}->alt,
        '#parents' => ['attributes', 'alt'],
        '#access' => !empty($settings['alt_field']) && ($filter_html->status === FALSE || !empty($allowed_attributes['alt'])),
      ];
    }

    // When Drupal core's filter_align is being used, the text editor offers the
    // ability to change the alignment.
    $form['align'] = [
      '#title' => $this->t('Align'),
      '#type' => 'radios',
      '#options' => [
        'none' => $this->t('None'),
        'left' => $this->t('Left'),
        'center' => $this->t('Center'),
        'right' => $this->t('Right'),
      ],
      '#default_value' => empty($media_embed_element['data-align']) ? 'none' : $media_embed_element['data-align'],
      '#attributes' => ['class' => ['container-inline']],
      '#parents' => ['attributes', 'data-align'],
      '#access' => $filter_align->status && ($filter_html->status === FALSE || !empty($allowed_attributes['data-align'])),
    ];

    // When Drupal core's filter_caption is being used, the text editor offers
    // the ability to in-place edit the media's caption: show a toggle.
    $form['caption'] = [
      '#title' => $this->t('Caption'),
      '#type' => 'checkbox',
      '#default_value' => $has_caption === 'true',
      '#parents' => ['hasCaption'],
      '#access' => $filter_caption->status && ($filter_html->status === FALSE || !empty($allowed_attributes['data-caption'])),
    ];

    $view_mode_options = array_intersect_key($this->entityDisplayRepository->getViewModeOptionsByBundle('media', $media->bundle()), $media_embed_filter->settings['allowed_view_modes']);
    $default_view_mode = static::getViewModeDefaultValue($view_mode_options, $media_embed_filter, $media_embed_element['data-view-mode']);

    $form['view_mode'] = [
      '#title' => $this->t("Display"),
      '#type' => 'select',
      '#options' => $view_mode_options,
      '#default_value' => $default_view_mode,
      '#parents' => ['attributes', 'data-view-mode'],
      '#access' => count($view_mode_options) >= 2,
    ];

    // Store the default from the MediaEmbed filter, so that if the selected
    // view mode matches the default, we can drop the 'data-view-mode'
    // attribute.
    $form_state->set('filter_default_view_mode', $media_embed_filter->settings['default_view_mode']);

    if ((empty($form['alt']) || $form['alt']['#access'] === FALSE) && $form['align']['#access'] === FALSE && $form['caption']['#access'] === FALSE && $form['view_mode']['#access'] === FALSE) {
      $format = $editor->getFilterFormat();
      $warning = $this->t('There is nothing to configure for this media.');
      $form['no_access_notice'] = ['#markup' => $warning];
      if ($format->access('update')) {
        $tparams = [
          '@warning' => $warning,
          '@edit_url' => $format->toUrl('edit-form')->toString(),
          '%format' => $format->label(),
        ];
        $form['no_access_notice']['#markup'] = $this->t('@warning <a href="@edit_url">Edit the text format %format</a> to modify the attributes that can be overridden.', $tparams);
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['save_modal'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      // No regular submit-handler. This form only works via JavaScript.
      '#submit' => [],
      '#ajax' => [
        'callback' => '::submitForm',
        'event' => 'click',
      ],
      // Prevent this hidden element from being tabbable.
      '#attributes' => [
        'tabindex' => -1,
      ],
    ];

    return $form;
  }

}
