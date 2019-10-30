/**
 * Augment CKEditorAddDefinitions with undo, redo, save state, load state functions.
 */
var CKEditorAddDefinitions = (function(my) {
  'use strict';

  // Undo and redo stacks.
  my.undoStack = [];
  my.redoStack = [];

  // DictionaryState constructor.
  function DictionaryState(stateLabel, display, highlighted, stripped, raw, dict, added, offset, rawRepl,
                           rawReplacementsNodes, curr, dList, prevMapped, order, term, filter) {

    this.label = stateLabel;

    this.displayContent = display.cloneNode(true);
    this.highlightedContent = highlighted;
    this.strippedContent = stripped;
    this.rawContent = raw;

    this.numAddedTerms = added;
    this.currStep = curr;
    this.previouslyMapped = prevMapped.slice(0);
    this.termOrder = [];
    copyOrder(this.termOrder, order);
    this.filterSelect = document.createElement("select");
    copySelect(this.filterSelect, filter);
    this.dictSelect = document.createElement("select");
    copySelect(this.dictSelect, term);

    this.rawReplacements = [];
    copyRawReplacements(this.rawReplacements, rawRepl);
    this.rawReplacementsNodes = [];
    this.rawReplacementsNodes = rawReplacementsNodes.slice(0);

    this.startIndexOffset = {};

    copyAttrs(this.startIndexOffset, offset);
  }

  // Save state and push onto correct stack; clear redo stack if needed.
  my.saveState = function(stateLabel, keepRedo, redoMode) {

    var newState = new DictionaryState(stateLabel, my.displayContent, my.highlightedContent, my.strippedContent, my.rawContent,
      my.dictionary, my.numAddedTerms, my.startIndexOffset, my.rawReplacements, my.rawReplacementsNodes,
      my.currStep, my.dictList, my.previouslyMapped, my.termOrder, my.dictSelect, my.filterSelect);

    if (!keepRedo)
      my.redoStack.length = 0;

    [my.undoStack, my.redoStack][+(redoMode==true)].push(newState);
  }

  // Load from a particular state.
  my.loadState = function(stateObj) {
    my.displayContent = stateObj.displayContent;
    my.numAddedTerms = stateObj.numAddedTerms;
    my.currStep = stateObj.currStep;
    my.previouslyMapped = stateObj.previouslyMapped;
    my.termOrder = [];
    copyOrder(my.termOrder, stateObj.termOrder);

    my.rawReplacements = stateObj.rawReplacements;
    my.rawReplacementsNodes = stateObj.rawReplacementsNodes;

    copySelect(my.filterSelect, stateObj.filterSelect);
    copySelect(my.dictSelect, stateObj.dictSelect);

    my.populateForm(my.currStep);
  }

  // Pop from undo stack, push to redo stack, load.
  my.undo = function() {
    if (my.undoStack.length > 0) {
      var tmpState = my.undoStack.pop();
      my.saveState(tmpState.label, true, true);
      my.loadState(tmpState);
    }
  }

  // Pop from redo stack, push to undo stack, load.
  my.redo = function() {
    if (my.redoStack.length > 0) {
      var tmpState = my.redoStack.pop();
      my.saveState(tmpState.label, true, false)
      my.loadState(tmpState);
    }
  }

  // Update the undo / redo text and disabled states.
  my.undoRedoUpdate = function() {

    var showUndo = my.undoRedoEnabled && my.undoStack.length > 0;
    var showRedo = my.undoRedoEnabled && my.redoStack.length > 0;

    if (showUndo) {
      my.undoBtn.title = "Undo "+my.undoStack[my.undoStack.length-1].label;
      my.enableCKEditorDialogButton(my.undoBtn);
    } else {
      my.undoBtn.title = "Undo";
      my.disableCKEditorDialogButton(my.undoBtn);
    }

    if (showRedo) {
      my.redoBtn.title = "Redo "+my.redoStack[my.redoStack.length-1].label;
      my.enableCKEditorDialogButton(my.redoBtn);
    } else {
      my.redoBtn.title = "Redo";
      my.disableCKEditorDialogButton(my.redoBtn);
    }
  }

  // Deep copy private functions ---------------------------

  // Copy select DOM element.
  function copySelect(dest, src) {

    dest.options.length = 0;

    for (var i = 0; i < src.options.length; i++)
      dest.appendChild(src.options[i].cloneNode(true)); // hack to make IE happy

    dest.selectedIndex = src.selectedIndex;
  }

  // Copy JS object consisting only of basic attrs that can be copied with = operator.
  function copyAttrs(dest, src) {
    for (var attr in src) {
      dest[attr] = src[attr];
    }
  }

  // Copy 2D array of numbers.
  function copyOrder(dest, src) {
    dest.length = 0;

    for (var i = 0; i < src.length; i++) {
      dest.push(src[i].slice(0));
    }
  }

  // Copy 3D array.
  function copyRawReplacements(dest, src) {

    dest.length = 0;

    for (var i = 0; i < src.length; i++) {
      var newNodeInfo = [];
      for (var j = 0; j < src[i].length; j++) {
        newNodeInfo.push(src[i][j].slice(0));
      }
      dest.push(newNodeInfo);
    }
  }

  return my;

})(CKEditorAddDefinitions || {});
