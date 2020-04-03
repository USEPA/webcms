/**
 * Main definition for CKEditorAddDefinitions module, a singleton used to namespace functions for the native CKEditor dictionary plugin.
 */
var CKEditorAddDefinitions = (function(my) {
  'use strict';

  /**
   * Register CKEditor dictionary plugin.
   */
  CKEDITOR.plugins.add('epaAddDefinitions',
    {
      init: function(editor) {
        // module settings. enable all by default
        my.dictionaryModulePath = this.path;
        my.glossaryFilterEnabled = editor.config.glossaryFilterEnabled;
        my.undoRedoEnabled = editor.config.undoRedoEnabled;
        my.firstOccurrenceOnly = editor.config.firstOccurrenceOnly;
        my.serviceEndpoint = editor.config.serviceEndpoint;

        init_dictionary(editor, this);
        editor.ui.addButton('epaAddDefinitionsButton',
          {
            label: 'Add Definitions',
            command: 'dictionaryTermAdd',
            icon: this.path+'images/dictionary.png'
          });
      }
    });

  /**
   * Initialization function for the "Add Dictionary Terms" button.
   */
  function init_dictionary(editor, _this) {

    // Command to open the CKEditor dialog
    editor.addCommand('dictionaryDialog', new CKEDITOR.dialogCommand('dictionaryDialog'));

    // Command for the Add Definitions button. If text is selected in the editor, open the dialog against the selected text.
    editor.addCommand('dictionaryTermAdd', {
      exec: function(editor) {

        var selected = editor.getSelectedHtml();

        if (selected)
          editor.execCommand('dictionaryDialog');
        else
          alert('Please select some text to add definitions.');
      },
      async: true
    });

    // Reset state and POST to REST service to retrieve dictionary terms against selected text. Called immediately after the dialog is opened.
    editor.addCommand('dictionaryPreprocess',
      {
        exec: function(editor) {
          my.dictionaryPreprocess(editor);
        },
        async: true
      });

    var scripts_path = _this.path + 'scripts/';

    // load dialog stored in external js
    CKEDITOR.dialog.add('dictionaryDialog', scripts_path + 'ckeditor-add-definitions-dialog.js');

    // load additional module components
    CKEDITOR.scriptLoader.load(scripts_path + 'ckeditor-add-definitions-controller.js');
    CKEDITOR.scriptLoader.load(scripts_path + 'ckeditor-add-definitions-highlight.js');
    CKEDITOR.scriptLoader.load(scripts_path + 'ckeditor-add-definitions-state.js');
    CKEDITOR.scriptLoader.load(scripts_path + 'ckeditor-add-definitions-ie-augment.js');
  }

  return my;
})(CKEditorAddDefinitions || {});
