/**
 * Main controller logic for CKEditor plugin.
 */
var CKEditorAddDefinitions = (function(my, $, undefined) {
  'use strict';

  // Constants
  my.maxDisplayLen = 100;
  my.labelNoMoreTermsFound = 'No more terms found. ';
  my.labelSelectTermForStart = 'Select definition for term <strong>';
  my.labelSelectTermForEnd = '</strong>';

  // State variables used for each cycle
  my.numAddedTerms; // number of terms added in one cycle before a commit
  my.startIndexOffset = {};

  my.currStep = 0;
  my.dictList = [];
  my.dictionary;

  // Content buffers
  my.displayContent;
  my.highlightedContent;
  my.strippedContent;
  my.rawContent;

  my.rawContentParent;

  my.rawReplacements = [];
  my.rawReplacementsNodes = [];
  my.previouslyMapped = [];

  // Timeout for JSONP request to Term Lookup web service
  my.errorTimeouts = {};

  // Keep track of the selected term for Removal functions
  my.selectedTerm = {};

  // Stores a list of index-index pairs to give the correct order of
  // appearance in the text of each dictionary term, of the form
  // [(term number), (index number within list of term indices)]
  // Example:
  // text = "epa toxic epa";
  // dict = matches: [{index: [0,10],term:"epa",...},{index:[4],term:"toxic",...}];
  // termOrder = [[0,0], [1,0], [0,1]];
  my.termOrder;

  // Reset state and POST to REST service to retrieve dictionary terms against selected text. Called immediately after the dialog is opened.
  my.dictionaryPreprocess = function(editor) {
    my.numAddedTerms = 0;
    my.startIndexOffset.display = 0;
    my.startIndexOffset.highlight = 0;
    my.startIndexOffset.raw = 0;
    my.undoStack = [];
    my.redoStack = [];

    var selected = editor.getSelectedHtml();
    my.rawContent = getRawContent(editor);

    my.displayContent = document.createElement('span');
    my.rawContentParent = my.rawContent.getCommonAncestor().$;
    my.strippedContent = '';

    if (my.rawContentParent) {
      my.strippedContent = stripAll(selected);
      my.displayContent.innerHTML = selected;
    }

    my.errorTimeouts[setTimeout(my.setErrorState, 30000)] = 1;

    $.ajax({
      type: "POST",
      url: '/epa_wysiwyg/definitions/dialog',
      dataType: "script",
      data: {
        text: decodeURI(my.strippedContent)
      },
      success: function(data) {
        // JSONP: append <script> to <head>
        // Setting script contents with $.html won't work in IE8
        // due to jQuery bug: http://bugs.jquery.com/ticket/10603
        var script = document.createElement('script');

        // IE8 uses the text attribute rather than innerHTML
        if (navigator.appName == 'Microsoft Internet Explorer')
          script.text = data;
        else
          script.innerHTML = data;

        $('head').append(script);
      },
      timeout: 30000,
      error: function(request, textStatus, errorThrown) {
        my.setErrorState(request, textStatus, errorThrown);
      }
    });
  }

  // JSONP request callback
  my.dictionaryCallback = function(dict, saveFilter) {

    clearAllTimeouts();

    if(!saveFilter) {
      my.dictionary = dict;
      my.dictList = {"len": 0, "dicts": {}};

      my.termOrder = [];
      my.rawReplacements = [];
      my.rawReplacementsNodes = [];

      if (dict.matches != undefined) {
        for (var k = 0; k < dict.matches.length; k++) {
          for (var i = 0; i < dict.matches[k].index.length; i++) {
            my.termOrder.push([parseInt(k),parseInt(i), 0]);
          }

          for (var i in dict.matches[k].definitions) {

            if (my.dictList["dicts"][dict.matches[k].definitions[i].dictionary] != undefined) {
              if (!contains(my.dictList["dicts"][dict.matches[k].definitions[i].dictionary], dict.matches[k].term)) {
                my.dictList["dicts"][dict.matches[k].definitions[i].dictionary].push(dict.matches[k].term)
              }
            } else {
              my.dictList["dicts"][dict.matches[k].definitions[i].dictionary] = [dict.matches[k].term];
              my.dictList.len++;
            }
          }
        }
      }

      // Sort using the word position in the text as the comparator
      my.termOrder.sort(function(a, b) {
        var order = my.dictionary.matches[a[0]].index[a[1]] - my.dictionary.matches[b[0]].index[b[1]];
        if (order == 0) // break tie with term length -- longer term comes first
          order += my.dictionary.matches[b[0]].term.length - my.dictionary.matches[a[0]].term.length;
        return order;
      });
    }

    my.populateDictionaryFilter(saveFilter);
    my.populateForm(my.currStep = my.advanceStep(0, true, my.selectedFilters()));
  }


  /**
   * Populate the "Filter by Dictionary" multi select box with the list of dictionaries
   * as returned from the Term Lookup REST service.
   */
  my.populateDictionaryFilter = function(saveFilter) {

    if (!my.glossaryFilterEnabled) {
      filterLabel.innerHTML = '';
      my.filterSelect.style.visibility = 'hidden';
      return;
    }

    var dictOrder = Object.keys(my.dictList.dicts);

    dictOrder.sort(function(a,b) {
      if(a < b) return -1;
      if(a > b) return 1;
      return 0;
    });

    if (saveFilter) {
      var selectedOpts = my.selectedFilters();
    }

    my.filterSelect.options.length = 0;
    my.filterSelect.options[0] = new Option("All Dictionaries", "");
    my.filterSelect.options[0].selected = true;

    for (var i = 0, j = 0; i < dictOrder.length; i++) {
      var key = dictOrder[i];
      if (my.termOrder[my.advanceStep(0, true, [key])] != undefined || (saveFilter && selectedOpts.indexOf(key) > -1)) {

        var wordList = my.dictList["dicts"][key];
        wordList.sort();
        var joinedWordList = wordList.join(", ");
        var numTotal = my.dictList["dicts"][key].length;
        var numVisible = numTotal;
        var wordListLimit = my.maxDisplayLen - key.length - 10;

        if (joinedWordList.length > wordListLimit) {
          for (numVisible = numVisible -1; joinedWordList.length > wordListLimit; numVisible--) {
            joinedWordList = my.dictList["dicts"][key].slice(0, numVisible).join(", ");
          }
        }

        if (numVisible < my.dictList["dicts"][key].length) {
          joinedWordList += ", ..., "+(numTotal - numVisible)+" more";
        }

        var display = key + " (" + joinedWordList + ")";


        my.filterSelect.options[j+1] = new Option(display, key);
        j++;
      }
    }

    if (saveFilter) {
      for (var i = 0; i < my.filterSelect.options.length; i++)
        my.filterSelect.options[i].selected = selectedOpts.indexOf(my.filterSelect.options[i].value) > -1;
    }
  }

  /**
   * Populate the dialog window's form control's and preview pane based on the currently active term.
   */
  my.populateForm = function(step, prevStep) {

    // Step out of bounds or step already applied
    if (my.termOrder[step] != undefined && my.termOrder[step][2] > 0) {
      return;
    }

    // Apply previous step
    if (prevStep != undefined && my.termOrder[prevStep] != undefined) {
      my.applyStep(prevStep);
    }

    my.startIndexOffset.display = computeDisplayOffset(step);

    if (my.termOrder[step] == undefined) {
      my.setFinishedState();
    } else {
      my.setDefaultState(step);
    }

    var currFilters = my.selectedFilters();

    // Highlight matched terms
    my.Highlighter.highlightAll(step, currFilters);
    scrollToHighlighted(step);

    // Populate definitions for current term
    my.dictSelect.options.length = 0;

    if (my.termOrder[step] != undefined) {

      var matchNum = my.termOrder[step][0];
      var indexNum = my.termOrder[step][1];

      var sortedIndices = [];
      for (var x = 0; x < my.dictionary.matches[matchNum].definitions.length; x++) {
        sortedIndices.push(x);
      }

      sortedIndices.sort(function(a, b) {
        if(my.dictionary.matches[matchNum].definitions[a].dictionary < my.dictionary.matches[matchNum].definitions[b].dictionary) return -1;
        if(my.dictionary.matches[matchNum].definitions[a].dictionary > my.dictionary.matches[matchNum].definitions[b].dictionary) return 1;
        return 0;
      });

      for (var j = 0, n = 0; j < my.dictionary.matches[matchNum].definitions.length; j++) {

        if (currFilters.indexOf("") == -1 &&
          currFilters.indexOf(my.dictionary.matches[matchNum].definitions[sortedIndices[j]].dictionary) == -1)
          continue;

        var display = my.dictionary.matches[matchNum].definitions[sortedIndices[j]].dictionary + " -- ";
        var def = my.dictionary.matches[matchNum].definitions[sortedIndices[j]].definition;

        display += def;

        my.dictSelect.options[n] = new Option(display, encodeURI(JSON.stringify({"term": my.dictionary.matches[matchNum].term,
          "dict": my.dictionary.matches[matchNum].definitions[sortedIndices[j]].dictionary,
          "definition": my.dictionary.matches[matchNum].definitions[sortedIndices[j]].definition})));

        n++;
      }

      // Call the term change handler manually to reset the term definition preview
      my.termChange();
    }
  }

  /**
   * Handler for term selection drop down menu's onChange event.
   * Displays the preview text for the selected term, if one is selected.
   */
  my.termChange = function() {
    if (my.dictSelect.style.visibility == 'hidden')
      my.definitionDiv.innerHTML = '';
    else
      my.definitionDiv.innerHTML = JSON.parse(decodeURI(my.dictSelect.options[my.dictSelect.selectedIndex].value)).definition;
  }

  /**
   * Retrieve the index of the term in the text based on the specified step.
   */
  my.computeIndex = function(step) {

    if(my.termOrder[step] == undefined)
      return Infinity;

    var matchNum = my.termOrder[step][0];
    var indexNum = parseInt(my.termOrder[step][1]);
    var index = parseInt(my.dictionary.matches[matchNum].index[indexNum]);
    return index;
  }

  /**
   * Return whether or not the term already has a definition applied to it.
   */
  my.alreadyMapped = function(term) {

    for (var i = 0; i < my.previouslyMapped.length; i++) {
      if (my.previouslyMapped[i].term == term)
        return true;
    }

    return false;
  }

  /**
   * Compute the next step after the specified step. This depends on some user settings,
   * which dictionary filter is specified, whether or not overlap is permitted, and
   * whether or not a term already has a definition applied.
   * Filter value of "" means no filter.
   */
  my.advanceStep = function(step, allowOverlap, filters) {


    var prevStep = step - 1;
    var moved = true;

    do {

      moved = false;

      if (my.firstOccurrenceOnly) {
        // Skip words that are not first occurrences
        while (my.termOrder[step] != undefined && (my.termOrder[step][1] > 0 || my.alreadyMapped(my.dictionary.matches[my.termOrder[step][0]].term))) {
          step++;
        }
      }

      // Skip already added words
      while (my.termOrder[step] != undefined && my.termOrder[step][2] > 0) {
        step++;
        moved = true;
      }

      // Order of overlapping words -- sorted by longest first, so "toxic chemical" before "toxic"
      // Skip overlapping words -- if "toxic chemical" is added then "toxic" and "chemical" must be skipped
      if (!allowOverlap && prevStep >= 0) {
        var boundary = my.computeIndex(prevStep) + my.dictionary.matches[my.termOrder[prevStep][0]].term.length;
        while(boundary > my.computeIndex(step)) {
          step++;
          moved = true;
        }
      }

      // Skip words that overlap with a word that already has a definition
      for (var tmpStep = 0; tmpStep < my.termOrder.length; tmpStep++) {
        if (my.termOrder[tmpStep][2] > 0) { // tmpStep refers to a word with a definition
          var start = my.computeIndex(tmpStep);
          var end = start + my.dictionary.matches[my.termOrder[tmpStep][0]].term.length;

          var index;

          while (start <= (index = my.computeIndex(step)) && end >= index) {
            step++;
            moved = true;
          }
        }
      }

      // Skip words not within filter
      while(!passFilter(step, filters)) {
        step++;
        moved = true;
      }
    } while (moved);

    return step;
  }

  /**
   * Compute selected filters, which depends on user settings.
   */
  my.selectedFilters = function() {
    var opts = [];

    for (var i = 0; i < my.filterSelect.options.length; i++) {

      if (!my.glossaryFilterEnabled || my.filterSelect.options[i].selected) {
        opts.push(my.filterSelect.options[i].value);
        if (my.filterSelect.options[i].value == "")
          break;
      }
    }

    return opts;
  }

  /**
   * Prepare to apply the definition to the term. Computes a list of nodes and replacements,
   * which may be used to perform the actual replacement later, depending on which content buffer
   * is being worked on. The rawContent buffer may defer replacements until all steps are done,
   * while the displayContent buffer performs the replacement immediately.
   */
  my.applyTermReplace = function(content, searchMask, occurrenceNum, replaceMaskNode, nodeList, replacementList, applyNow) {

    if (searchMask.length == 0)
      return;

    var nth = 0;
    var walker = new my.NodeIterator(content), currentNode;

    while (walker.hasNext()) {

      currentNode = walker.nextNode();

      if (currentNode.hasChildNodes()) { // Only look at leaf nodes
        continue;
      }

      var goodnode = currentNode;

      if (currentNode.parentNode) { // May not have a parent

        var tempNode = currentNode;
        var skipNode = false;

        while (tempNode.parentNode) {
          if (tempNode.parentNode.tagName == "A") {
            skipNode = true;
            break;
          }

          tempNode = tempNode.parentNode;
        }

        if (skipNode)
          continue;

        if (currentNode.parentNode.tagName == "SPAN" && currentNode.parentNode.className == "termlookup-custom") {
          continue;
        }
      }

      var currentText = currentNode.nodeValue;
      var startIndex = 0, i;

      while ((i = currentText.toLowerCase().indexOf(searchMask, startIndex)) > -1) {

        startIndex = i + searchMask.length;

        // Make sure the match isn't a subword of another word
        // Check start boundary
        if ((i > 0 && /[a-zA-Z]/.test(currentText.charAt(i-1)))) {
          continue;
        }

        // Check end boundary
        if ((i + searchMask.length < currentText.length && /[a-zA-Z]/.test(currentText.charAt(i+searchMask.length)))) {
          continue;
        }

        nth++;

        if (nth == occurrenceNum) {
          var match = currentText.substr(i, searchMask.length);
          var newNode = true;

          for (var j = 0; j < nodeList.length; j++) {
            if (nodeList[j] == (currentNode)) {
              newNode = false;
              break;
            }
          }

          if (newNode) {
            nodeList.push(currentNode);
            replacementList.push([]);
            j = replacementList.length - 1;
          }
          replaceMaskNode.innerHTML = replaceMaskNode.innerHTML.replace(/%TERM%/g, match);

          replacementList[j].push([i, match, replaceMaskNode]);
        }
      }
    }

    if (applyNow) {
      my.replaceSelectedText(nodeList, replacementList);
    }
  }

  /**
   * Perform the DOM manipulations to replace the selected text.
   */
  my.replaceSelectedText = function(nodeList, replacementList) {

    for (var nodeNum = 0; nodeNum < nodeList.length; nodeNum++) {

      var node = nodeList[nodeNum];

      replacementList[nodeNum].sort(function(a, b) {
        return b[0] - a[0];
      });

      for (var termNum = 0; termNum < replacementList[nodeNum].length; termNum++) {

        var termInfo = replacementList[nodeNum][termNum];
        var pos = termInfo[0];
        var searchMask = termInfo[1];
        var replaceMaskNode = termInfo[2];

        var afterSplitNode = node.splitText(pos);
        afterSplitNode.nodeValue = afterSplitNode.nodeValue.substr(searchMask.length);
        node.parentNode.insertBefore(afterSplitNode, node.nextSibling);
        node.parentNode.insertBefore(replaceMaskNode, afterSplitNode);
      }
    }
  }

  /**
   * Apply the specified step by updating the content buffers with the selected term definition applied to the term.
   */
  my.applyStep = function(step) {

    my.numAddedTerms++;

    var entry = JSON.parse(decodeURI(my.dictSelect.options[my.dictSelect.selectedIndex].value));

    var matchNum = my.termOrder[step][0];
    var indexNum = parseInt(my.termOrder[step][1]);

    var displaySearchPos = parseInt(my.dictionary.matches[matchNum].index[indexNum]) + my.startIndexOffset.display;

    // %TERM% is used as a placeholder for the term as it appears in text ("EPA" rather than "epa")
    // The correct form is determined at a later time, so the placeholder is needed here
    var replaceMaskNode = createTermNode(indexNum+1, entry.definition);
    my.applyTermReplace(my.rawContentParent, entry.term, indexNum+1, replaceMaskNode, my.rawReplacementsNodes, my.rawReplacements);
    my.applyTermReplace(my.displayContent, entry.term, indexNum+1, replaceMaskNode, [], [], true);

    my.termOrder[step][2] = (
      '<span class="definition js-definition"><button class="definition__trigger js-definition__trigger">' +
      entry.term +
      '</button><span class="definition__tooltip js-definition__tooltip" role="tooltip"><dfn class="definition__term">' +
      entry.term +
      '</dfn>' +
      entry.definition +
      '</span></span>'
    ).length;
  }

  my.setDefaultState = function(step) {

    my.addTermBtn.style.visibility = 'visible';
    my.skipBtn.style.visibility = 'visible';

    my.enableCKEditorDialogButton(my.addTermBtn);
    my.enableCKEditorDialogButton(my.skipBtn);

    my.dictSelect.style.visibility = 'visible';
    var matchNum = my.termOrder[step][0];
    var indexNum = my.termOrder[step][1];
    var term = my.dictionary.matches[matchNum].term; // Note: this term will be in the lower case form.

    my.dictLabel.innerHTML = my.labelSelectTermForStart + term + my.labelSelectTermForEnd;

    my.statusDiv.innerHTML = 'Term '+(step+1) + ' of ' + my.termOrder.length
    + '; found entries in '+my.dictList.len+' dictionaries.';
    my.undoRedoUpdate();
  }

  my.setFinishedState = function() {
    my.disableCKEditorDialogButton(my.addTermBtn);
    my.disableCKEditorDialogButton(my.skipBtn);
    my.dictSelect.style.visibility = 'hidden';
    my.dictLabel.innerHTML = my.labelNoMoreTermsFound;
    my.definitionDiv.innerHTML = '';
    my.statusDiv.innerHTML = '';
    my.undoRedoUpdate();
  }

  my.setErrorState = function(request, textStatus, errorThrown) {
    my.addTermBtn.style.visibility = 'hidden';
    my.skipBtn.style.visibility = 'hidden';
    my.previewDiv.innerHTML = "";
    my.dictLabel.innerHTML = "Error: Dictionaries could not be loaded.";
    my.dictSelect.options.length = 0;
    my.dictSelect.style.visibility = 'hidden';
    my.statusDiv.innerHTML = '';
    my.undoRedoUpdate();
  }

  my.enableCKEditorDialogButton = function(btn) {
    btn.disabled = "";
    btn.removeAttribute("disabled");

    if (btn.title == my.addTermBtn.title || btn.title == my.skipBtn.title) {
      btn.style.background = ""; // For all non-IE browsers.
      btn.removeAttribute("style"); // For IE8.
    }

    btn.style.opacity = 1;
    btn.style.outline = '';

  }

  my.disableCKEditorDialogButton = function(btn) {
    btn.disabled = "disabled";
    if (btn.title == my.addTermBtn.title || btn.title == my.skipBtn.title) {
      btn.style.background = "#ccc";
    }
    btn.style.opacity = .35; // IE will ignore this, but it will stylize based on the "disabled" attribute instead.
    btn.style.outline = 'none';
  }

  // Begin private helpers.

  function getRawContent(editor) {

    var selection = editor.getSelection();

    if (selection) {
      var bookmarks = selection.createBookmarks();
      var range = selection.getRanges()[0];
      var fragment = range.clone().cloneContents();
      selection.selectBookmarks(bookmarks);
      return range;
    }
  }

  // Replace %1%, %2%, ... with their definitions.
  // Needed if editing text where definitions were already present.
  function applyOldDefs(content) {
    return content.replace(/%(.+?)%/g, function(match, sub1) {
      return my.previouslyMapped[sub1-1] ? my.previouslyMapped[sub1-1].match : match;
    });
  }

  // Check if the given step should be skipped or not based on filter.
  function passFilter(step, filters) {

    if (filters.indexOf("") > -1 || my.termOrder[step] == undefined) {
      return true;
    }

    var defs = my.dictionary.matches[my.termOrder[step][0]].definitions;

    for (var i in defs) {
      if (filters.indexOf(defs[i].dictionary) > -1) {
        return true;
      }
    }

    return false;
  }

  // Create and return a new DOM element which contains all markup for a term and associated definition.
  function createTermNode(occurrenceNum, entryDefinition) {
    var entryTerm = '%TERM% ';
    var definition = document.createElement('span');
    definition.setAttribute('class', 'definition js-definition');

    var button = document.createElement('button');
    button.setAttribute('class', 'definition__trigger js-definition__trigger');
    button.appendChild(document.createTextNode(entryTerm));

    var tooltip = document.createElement('span');
    tooltip.setAttribute('class', 'definition__tooltip js-definition__tooltip');
    tooltip.setAttribute('role', 'tooltip');

    var term = document.createElement('dfn');
    term.setAttribute('class', 'definition__term');
    term.appendChild(document.createTextNode(entryTerm));

    tooltip.appendChild(term);
    tooltip.appendChild(document.createTextNode(entryDefinition));
    definition.appendChild(button);
    definition.appendChild(tooltip);

    return definition;
  }

  function computeDisplayOffset(step) {

    var offset = 0;

    for (var i = 0; i < step; i++) {
      offset += my.termOrder[i][2];
    }

    my.statusDiv.innerHTML += "; "+offset;

    return offset;
  }

  // Scroll the preview panel to the currently highlighted term.
  function scrollToHighlighted(step) {

    var activeElement = my.innerDoc.getElementById('termlookup-active-highlight');

    if (activeElement) {
      my.previewIframe.contentWindow.scrollTo(0, activeElement.offsetTop);
    }
  }

  // Check if object contains value.
  function contains(a, obj) {
    for (var i = 0; i < a.length; i++) {
      if (a[i] === obj) {
        return true;
      }
    }
    return false;
  }

  // Clear timeouts which were set when waiting for JSONP response.
  function clearAllTimeouts() {
    for (var timeout in my.errorTimeouts) {
      clearTimeout(timeout);
    }

    my.errorTimeouts = {};
  }

  function stripAll(content) {
    return stripHtml(stripAnchors(stripNbsp(stripDefs(content)))).trimRight();
  }

  function stripHtml(content) {
    return content.replace(/<[^<|>]+?>/gi,' ');
  }

  function stripAnchors(content) {
    return content.replace(/<a[^<|>]*?>.*?<\/a>/gi, ' ');
  }

  function stripNbsp(content) {
    return content.replace(/[\s\xA0]+/g, ' ');
  }

  function stripDefs(content) {
    my.previouslyMapped = [];
    // Regex finds previous definitions OR raw text in the format %number%
    // and replaces these to %number% to be replaced back in later.
    // The latter search criteria is needed, because otherwise the raw text will
    // also get replaced by a definition when it should not.
    return content.replace(/<a class="?termlookup-tooltip"?.+?>(.+?)<span class="?termlookup-custom.+?<\/a>|%.+?%/gi, function(match, term) {
      my.previouslyMapped.push({'match': match, 'term': term});
      // Must encode the percent character (%).
      return '%25'+my.previouslyMapped.length+'%25';
    });
  }

  return my;

})(CKEditorAddDefinitions || {}, jQuery);
