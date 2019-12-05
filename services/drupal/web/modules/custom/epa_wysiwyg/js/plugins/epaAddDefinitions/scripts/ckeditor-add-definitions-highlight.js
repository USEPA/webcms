/**
 * Augment CKEditorAddDefinitions with Highlight submodule to handle all term-highlighting
 * routines on the dialog window's preview pane.
 */
var CKEditorAddDefinitions = (function(my, undefined) {
  'use strict';

  my.Highlighter = {
    highlightAll: function (step, filters) {
      my.highlightedContent = my.displayContent.cloneNode(true);
      my.startIndexOffset.highlight = 0;

      var boundary = undefined;

      for (var i = 0; i < my.termOrder.length; i++) {

        if (i < my.currStep) {
          continue;
        } else if (boundary != undefined) {
          while (boundary > my.computeIndex(i)) i++;
        }

        if (my.termOrder[i] == undefined)
          break;

        var matchNum = my.termOrder[i][0];
        var indexNum = my.termOrder[i][1];

        if (my.firstOccurrenceOnly && (indexNum > 0 || my.alreadyMapped(my.dictionary.matches[my.termOrder[i][0]].term)) )
          continue;

        var defs = my.dictionary.matches[matchNum].definitions;

        var doHighlight = false;

        for (var j in defs) {
          if (filters.indexOf("") > -1 ||
            filters.indexOf(my.dictionary.matches[matchNum].definitions[j].dictionary) > -1) {
            doHighlight = true;
            break;
          }
        }

        if (doHighlight) {
          highlightTerm(i, i == step);
          var boundary = my.computeIndex(i) + my.dictionary.matches[matchNum].term.length;
        }
      }

      while(my.previewDiv.firstChild) {
        my.previewDiv.removeChild(my.previewDiv.firstChild);
      }

      my.previewDiv.appendChild(my.highlightedContent);
    }
  };

  function highlightTerm(step, activeHighlight) {

    var color = 'def';

    if (activeHighlight)
      color = 'FFEB57';

    if (my.termOrder[step] == undefined || my.termOrder[step][2] > 0) {
      return;
    }

    var matchNum = my.termOrder[step][0];
    var indexNum = my.termOrder[step][1];

    var term = my.dictionary.matches[matchNum].term;

    var replaceMaskNode = createHighlightNode(activeHighlight, color);

    my.applyTermReplace(my.highlightedContent, term, indexNum+1, replaceMaskNode, [], [], true);
  }

  function createHighlightNode(activeHighlight, color) {

    var span = document.createElement('span');

    span.setAttribute('style', 'background-color:#'+color);
    span.innerHTML = '%TERM%';

    if (activeHighlight) {
      var namedAnchor = document.createElement('a');
      namedAnchor.setAttribute('id', 'termlookup-active-highlight');
      span.appendChild(namedAnchor);
    }

    return span;
  }

  return my;

})(CKEditorAddDefinitions || {});
