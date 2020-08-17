/**
 * Add dialog for Dictionary plugin to CKEditor and related handlers.
 */
var CKEditorAddDefinitions = (function(my) {
  'use strict';

  // Cached dialog box DOM elements.
  my.addTermBtn;
  my.skipBtn;
  my.undoBtn;
  my.redoBtn;
  my.dictLabel;
  my.dictSelect;

  my.previewIframe;
  my.innerDoc;

  my.previewDiv;
  my.statusDiv;
  my.toolbarDiv;
  my.definitionDiv;
  my.filterLabel;
  my.filterSelect;

  // Register dialog with CKEditor.
  CKEDITOR.dialog.add('dictionaryDialog', function(editor) {
      var tmpDialog = {
        title: 'Add Dictionary Terms',
        minWidth: 650,
        minHeight: 450,
        contents:
          [
            {
              id: 'general',
              label: 'Settings',
              elements: [
                {
                  type: 'html',
                  id: 'dialog_toolbar',
                  label: 'Toolbar',
                  html:  '<span id="dictionary_dialog_toolbar" class="cke_toolgroup">'+
                  '<span class="cke_button">'+
                  '<a href="javascript:void(0);" hidefocus="true" style="opacity:1" id="cke_918_uiElement" class="cke_button__undo_icon" href="javascript:void(\'Undo\')" title="Undo" role="button" aria-labelledby="cke_918_label" onclick="CKEditorAddDefinitions.undoOnClick(); return false;">'+
                  '<span class="cke_button_icon" style="float: none;">&nbsp;</span>'+
                  '<span id="cke_918_label" class="cke_label">Undo</span>'+
                  '</a>'+
                  '</span>'+
                  '<span class="cke_button">'+
                  '<a href="javascript:void(0);" hidefocus="true" style="opacity:1" id="cke_919_uiElement" class="cke_button__redo_icon" href="javascript:void(\'Redo\')" title="Redo" role="button" aria-labelledby="cke_199_label" onclick="CKEditorAddDefinitions.redoOnClick(); return false;" aria-disabled="true">'+
                  '<span class="cke_button_icon" style="float: none;">&nbsp;</span>'+
                  '<span id="cke_919_label" class="cke_label">Redo</span>'+
                  '</a>'+
                  '</span>'+
                  '</span>'
                },
                {
                  type: 'select',
                  multiple: 'true',
                  'default': '',
                  id: 'filter',
                  label: 'Filter by Dictionary',
                  items: [],
                  className: 'add-definitions dictionary-filter',
                  onChange: function(e) {
                    my.dictionaryCallback(my.dictionary, true);
                  }
                },
                {
                  type: 'html',
                  id: 'matched_words',
                  label: 'Matched Words',
                  html:  '<iframe id="preview_iframe"></iframe>',
                  style: 'width: 650px'

                },
                {
                  type: 'select',
                  style: 'width:650px',
                  id: 'dictionary',
                  label: 'Dictionary',
                  items: [],
                  onChange: function(e) {
                    my.termChange();
                  }
                },
                {
                  type: 'html',
                  id: 'full_definition',
                  label: 'Full Definition',
                  html: '<div id="definition_pane"></div>'
                },
                {
                  type: 'hbox',
                  align: 'left',
                  children: [
                    {
                      type: 'button',
                      id: 'add_term',
                      label: 'Add Term',
                      onClick: function() {
                        if (my.addTermBtn.disabled != "disabled") {
                          my.saveState('Add Term "'+getCurrentTerm()+'"');
                          addTermAction();
                        }
                      },
                      style: 'float:left'
                    },
                    {
                      type: 'button',
                      id: 'skip_term',
                      label: 'Skip',
                      onClick: function() {
                        if (my.skipBtn.disabled != "disabled") {
                          my.saveState('Skip Term "'+getCurrentTerm()+'"');
                          skipTermAction();
                        }

                      }
                    },
                    {
                      type: 'html',
                      html: '<div style="width:100%;"></div>'
                    }
                  ],
                  widths: ['10','10','800']
                },

                {
                  type: 'html',
                  id: 'status',
                  html: '<div id="status_pane"></div>'
                }
              ],
            }
          ],
        buttons: [
          {
            type: 'button',
            id: 'doneBtn',
            label: 'Apply',
            onClick: function() {

              var currentTerm = getCurrentTerm();

              if (!currentTerm || confirm('Definition for term "'+currentTerm+'" was not added. Proceed anyway?')) {
                CKEDITOR.dialog.getCurrent().hide(); // hack for IE8...
                my.replaceSelectedText(my.rawReplacementsNodes, my.rawReplacements);
              }
            }
          },
          CKEDITOR.dialog.cancelButton
        ]
      };

      return tmpDialog;
    }
  );

  // On dialog dictionary creation -- perform preprocessing steps like caching DOM elements in dialog.
  CKEDITOR.on('dialogDefinition', function(ev) {

    var dialogName = ev.data.name;
    var dialogDefinition = ev.data.definition;
    var dialog = ev.data.definition.dialog;

    if (dialogName == 'dictionaryDialog') {

      dialog.on('show', function (editor) {

        // Need to grab the dialog's DOM elements on first load only.
        if (my.addTermBtn == undefined) {

          var anchors = document.getElementsByTagName('a');

          for (var i = 0; i < anchors.length; i++) {
            if (anchors[i].title == "Add Term") {
              my.addTermBtn = anchors[i];

            } else if (anchors[i].title == "Skip") {
              my.skipBtn = anchors[i];
            } else if (anchors[i].title == "Undo") {
              my.undoBtn = anchors[i];
            } else if (anchors[i].title == "Redo") {
              my.redoBtn = anchors[i];
            } else if (anchors[i].title == "Apply") {
              anchors[i].className += " cke_dialog_ui_button_ok";
            }
          }

          var selects = document.getElementsByTagName('select');
          my.dictSelect = undefined;

          for (var i = 0; i < selects.length; i++) {
            if (selects[i].id.indexOf("_select") != -1) {
              if (selects[i].multiple)
                my.filterSelect = selects[i];
              else
                my.dictSelect = selects[i];
            }
          }

          var divs = document.getElementsByTagName('div');

          for (var i = 0; i < divs.length; i++) {
            if (divs[i].id == 'status_pane') {
              my.statusDiv = divs[i];
            } else if (divs[i].id == 'dictionary_dialog_toolbar') {
              my.toolbarDiv = divs[i];
            } else if (divs[i].id == 'definition_pane') {
              my.definitionDiv = divs[i];
            }
          }

          var content = '<!DOCTYPE html><head><link type="text/css" rel="stylesheet" href="' + my.dictionaryModulePath
            + 'epaAddDefinitions.preview.css"></link></head><body><div id="preview_pane" class="clearfix"></div></body></html>';

          my.previewIframe = document.getElementById('preview_iframe');
          my.innerDoc = my.previewIframe.contentWindow.document || my.previewIframe.contentDocument;

          my.innerDoc.open('text/html', 'replace');
          my.innerDoc.write(content);
          my.innerDoc.close();

          my.previewDiv = my.innerDoc.getElementById('preview_pane');

          var labels = document.getElementsByTagName('label');
          for (var i = 0; i < labels.length; i++) {
            if (labels[i].getAttribute('for') == my.dictSelect.id) {
              my.dictLabel = labels[i];
            } else if (labels[i].getAttribute('for') == my.filterSelect.id) {
              my.filterLabel = labels[i];
            }
          }
        }

        setInitialState();

        // Preprocessing steps, including JSONP call.
        editor.sender._.editor.execCommand('dictionaryPreprocess');
      });
    }
  });

  // Private helpers: ---------------------------------------------------------------------------------------

  // Add term button handler -- less save state.
  function addTermAction() {
    var prevStep = my.currStep;
    // Advance step. if "toxic chemical" was accepted, then "toxic" and "chemical" need to be skipped over.
    // Also skip things that get filtered out.
    my.currStep = my.advanceStep(++my.currStep, false, my.selectedFilters());

    my.populateForm(my.currStep, prevStep);
    my.populateDictionaryFilter(true);
  }

  // Skip button handler -- less save state.
  function skipTermAction() {
    var prevStep = my.currStep;
    // Advance step. if "toxic chemical" was accepted, then "toxic" and "chemical" need to be skipped over.
    // Also skip things that get filtered out.
    my.currStep = my.advanceStep(++my.currStep, true, my.selectedFilters());
    my.populateForm(my.currStep);
  }

  // Undo button handler -- call undo if available.
  my.undoOnClick = function() {
    if (my.undoBtn.disabled != "disabled")
      my.undo();
  }

  // Redo button handler -- call redo if available.
  my.redoOnClick = function() {
    if (my.redoBtn.disabled != "disabled")
      my.redo();
  }

  // Get the state of the dialog window before the JSONP dictionary callback has returned.
  function setInitialState() {
    my.addTermBtn.style.visibility = 'hidden';
    my.skipBtn.style.visibility = 'hidden';
    my.previewDiv.innerHTML = "";
    my.dictLabel.innerHTML = "Loading dictionaries...";
    my.dictSelect.options.length = 0;
    my.dictSelect.style.visibility = 'hidden';
    my.statusDiv.innerHTML = '';
    my.definitionDiv.innerHTML = '';
    my.undoRedoUpdate();
  }

  // Get the term which is currently highlighted in the dictionary dialog/
  function getCurrentTerm() {
    if (my.dictLabel.innerHTML != my.labelNoMoreTermsFound) {
      return my.dictLabel.innerHTML.substr(my.labelSelectTermForStart.length,
        my.dictLabel.innerHTML.length - (my.labelSelectTermForStart + my.labelSelectTermForEnd).length);
    }

    return null;
  }

  return my;

})(CKEditorAddDefinitions || {});
