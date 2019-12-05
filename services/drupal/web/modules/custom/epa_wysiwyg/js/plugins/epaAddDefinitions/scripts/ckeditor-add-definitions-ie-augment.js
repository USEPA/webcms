/**
 * Augment IE8 with necessary String, Array, and Iterator functions.
 */
var CKEditorAddDefinitions = (function(my) {
  'use strict';

  if (typeof console === "undefined") {
    console = {
      log: function () {
        return;
      }
    };
  }

  if(typeof String.prototype.trimRight !== 'function') {

    String.prototype.trimRight = function() {

      var index = this.length;
      while (this.charAt(--index) == ' ');

      return this.substr(0, index+1);
    };
  }

  if (typeof Array.prototype.indexOf !== 'function') {
    Array.prototype.indexOf = function (searchElement) {
      'use strict';
      if (this == null) {
        throw new TypeError();
      }

      var n, k, t = Object(this),
        len = t.length >>> 0;

      if (len === 0) {
        return -1;
      }

      n = 0;

      if (arguments.length > 1) {
        n = Number(arguments[1]);
        if (n != n) { // shortcut for verifying if it's NaN
          n = 0;
        } else if (n != 0 && n != Infinity && n != -Infinity) {
          n = (n > 0 || -1) * Math.floor(Math.abs(n));
        }
      }

      if (n >= len) {
        return -1;
      }

      for (k = n >= 0 ? n : Math.max(len - Math.abs(n), 0); k < len; k++) {
        if (k in t && t[k] === searchElement) {
          return k;
        }
      }

      return -1;
    };
  }

  // for IE8 support
  // http://whattheheadsaid.com/2010/10/a-safer-object-keys-compatibility-implementation
  Object.keys = Object.keys || (function () {
    var hasOwnProperty = Object.prototype.hasOwnProperty,
      hasDontEnumBug = !{toString:null}.propertyIsEnumerable("toString"),
      DontEnums = [
        'toString',
        'toLocaleString',
        'valueOf',
        'hasOwnProperty',
        'isPrototypeOf',
        'propertyIsEnumerable',
        'constructor'
      ],
      DontEnumsLength = DontEnums.length;

    return function (o) {
      if (typeof o != "object" && typeof o != "function" || o === null)
        throw new TypeError("Object.keys called on a non-object");

      var result = [];
      for (var name in o) {
        if (hasOwnProperty.call(o, name))
          result.push(name);
      }

      if (hasDontEnumBug) {
        for (var i = 0; i < DontEnumsLength; i++) {
          if (hasOwnProperty.call(o, DontEnums[i]))
            result.push(DontEnums[i]);
        }
      }

      return result;
    };
  })();

  my.NodeIterator = function(root) {
    this.rootNode = root;
    this.currentNode = root;
    this.nodeQueue = [];
    this.currentQueueIndex = 0;
    this.buildNodeQueue(root);
  }

  my.NodeIterator.prototype.hasNext = function() {
    return this.nodeQueue.length > this.currentQueueIndex;
  }

  my.NodeIterator.prototype.nextNode = function() {
    return this.nodeQueue[this.currentQueueIndex++];
    // return this.nodeQueue.shift(); // IE8 doesn't like shift? of course it doesn't
  }

  my.NodeIterator.prototype.buildNodeQueue = function(node) {
    if (node.nodeType === 3) {
      this.nodeQueue.push(node);
    }
    if (node.hasChildNodes()) {
      var child = node.firstChild;
      while (child) {
        this.buildNodeQueue(child);
        child = child.nextSibling;
      }
    }
  }

  /**
   * Retrieve HTML presentation of the current selected range, require editor
   * to be focused first.
   */
  CKEDITOR.editor.prototype.getSelectedHtml = function() {

    var selection = this.getSelection();

    if (selection) {
      var bookmarks = selection.createBookmarks();
      var range = selection.getRanges()[0];
      var fragment = range.clone().cloneContents();
      selection.selectBookmarks(bookmarks);
      var retval = "";
      var childList = fragment.getChildren();
      var childCount = childList.count();
      for (var i = 0; i < childCount; i++) {
        var child = childList.getItem(i);
        retval += (child.getOuterHtml ? child.getOuterHtml() : child.getText());
      }

      return retval;
    }
  };

  return my;
})(CKEditorAddDefinitions || {});
