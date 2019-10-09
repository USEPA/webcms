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
                  'class' : 'new',
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

      editor.on('setData', function( event ) {
        var data = $('<div>' + event.data.dataValue + '</div>'),
          now = new Date();
        now = now.getTime();

        data.find('ins[data-date]').each(function () {
          var date = $(this).data(),
            offset = 30 * 24 * 60 * 60 * 1000,
            expired = Date.parse(date.date) + offset;

          if (now < expired) {
            $(this).addClass('new');
          }
        });

        // JQuery doesn't have a toString, so this extracts the full html.
        event.data.dataValue = data.html();
      });

      editor.on('getData', function( event ) {
        var data = $('<div>' + event.data.dataValue + '</div>');
        data.find('ins.new').removeClass('new');

        // JQuery doesn't have a toString, so this extracts the full html.
        event.data.dataValue = data.html();
      });
    }
  });
})(CKEDITOR, jQuery);
