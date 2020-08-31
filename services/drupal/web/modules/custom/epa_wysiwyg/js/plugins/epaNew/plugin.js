(function (CKEDITOR, $) {
  'use strict';

  CKEDITOR.plugins.add('epaNew', {
    requires: ['button'],

    init: function(editor) {
      editor.addCommand('epaNewCommand', {
        exec : function(editor) {
          var  d = new Date(),
            month = d.getMonth() + 1,
            day = d.getDate(),
            year = d.getFullYear(),
            date = year + "," + month + "," + day,
            elementPath = editor.elementPath(),
            style = new CKEDITOR.style(
              {
                element : 'ins',
                attributes : {
                  'class' : 'epa-new',
                  'data-date' : date
                }
              }
            );
          editor[style.checkActive(elementPath, editor) ? 'removeStyle' : 'applyStyle'](style);
        }
      });

      editor.ui.addButton('epaNewButton', {
        label: 'New! Icon',
        command: 'epaNewCommand',
        icon: this.path + 'images/epaNew.gif'
      });
    }
  });
})(CKEDITOR, jQuery);
