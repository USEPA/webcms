<?php

namespace Drupal\epa_wysiwyg\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "epaAddDefinitions" plugin.
 *
 * NOTE: The plugin ID ('id' key) corresponds to the CKEditor plugin name.
 * It is the first argument of the CKEDITOR.plugins.add() function in the
 * plugin.js file.
 *
 * @CKEditorPlugin(
 *   id = "epaAddDefinitions",
 *   label = @Translation("EPA Add Definitions Icon")
 * )
 */
class EPAAddDefinitions extends CKEditorPluginBase {

  /**
   * Allow glossary to be filtered.
   */
  const GLOSSARY_FILTER_ENABLED = 1;

  /**
   * Allow ability to undo or redo in CKEditor.
   */
  const UNDO_REDO_ENABLED = 1;

  /**
   * Allow to only check first occurrence of word.
   */
  const FIRST_OCCURRENCE_ONLY = 1;

  /**
   * URL for Term Lookup Service.
   */
  const SERVICE_ENDPOINT = 'https://termlookup.epa.gov/termlookup/v1/terms';

  /**
   * {@inheritdoc}
   *
   * NOTE: The keys of the returned array corresponds to the CKEditor button
   * names. They are the first argument of the editor.ui.addButton() or
   * editor.ui.addRichCombo() functions in the plugin.js file.
   */
  public function getButtons() {
    // Make sure that the path to the image matches the file structure of
    // the CKEditor plugin you are implementing.
    return [
      'epaAddDefinitionsButton' => [
        'label' => $this->t('EPA Add Definitions Icon'),
        'image' => 'modules/custom/epa_wysiwyg/js/plugins/epaAddDefinitions/images/dictionary.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    // Make sure that the path to the plugin.js matches the file structure of
    // the CKEditor plugin you are implementing.
    return drupal_get_path('module', 'epa_wysiwyg') . '/js/plugins/epaAddDefinitions/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function isInternal() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDependencies(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return [
      'epa_wysiwyg/epa-add-definitions-admin',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [
      'glossaryFilterEnabled' => self::GLOSSARY_FILTER_ENABLED,
      'undoRedoEnabled' => self::UNDO_REDO_ENABLED,
      'firstOccurrenceOnly' => self::FIRST_OCCURRENCE_ONLY,
      'serviceEndpoint' => self::SERVICE_ENDPOINT,
    ];
  }

}
