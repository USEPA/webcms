(function (CKEDITOR) {
  'use strict';

  function StringBuffer() {
    this.buffer = [];
  }

  StringBuffer.prototype.append = function append(string) {
    this.buffer.push(string);
    return this;
  };

  StringBuffer.prototype.toString = function toString() {
    return this.buffer.join('');
  };

  CKEDITOR.plugins.add('epaBox', {
    requires: ['button', 'dialog'],

    init: function(editor) {
      var iconPath = this.path + 'images/epaBox.png';

      editor.addCommand('epaBoxCommand', new CKEDITOR.dialogCommand('epaBoxDialog'));

      editor.ui.addButton('epaBoxButton', {
        label: 'Related Info Box',
        command: 'epaBoxCommand',
        icon: iconPath
      });

      CKEDITOR.dialog.add('epaBoxDialog', function(editor) {
        return {
          title: 'EPA Box',
          minWidth: 400,
          minHeight: 200,
          contents: [{
            id: 'general',
            label: 'Settings',
            elements: [{
              type: 'select',
              id: 'float',
              label: 'Position:',
              validate: CKEDITOR.dialog.validate.notEmpty("You must select the position of the box."),
              items: [
                ['Floated Left', 'left'],
                ['Floated Right', 'right']
              ],
              default: 'right',
              commit : function(data) {
                data.position = this.getValue();
              }
            },
            {
              type: 'text',
              id: 'title',
              label: 'Box Title:',
              commit : function(data) {
                data.title = this.getValue();
              }
            }]
          }],
          onOk: function() {
            var dialog = this,
                data = {},
                e = editor.getSelection().getSelectedElement(),
                text = "",
                box = new StringBuffer();

            this.commitContent(data);

            if (e === null) {
              text = editor.getSelection().getSelectedText();
            }

            box.append('<div class="box box--related-info u-align-' + data.position + '">');

            if (data.title !== '') {
              box.append('<div class="box__title">' + data.title + '</div>');
            }

            box.append('<div class="box__content">');

            if (e !== null) {
              box.append(e.getHtml());
            }
            else if (text.length > 1) {
              box.append('<p>' + text + '</p>');
            }
            else {
              box.append('<p>Enter your box content here.</p>');
            }

            box.append('</div>');

            editor.insertHtml(box.toString());
          }
        };
      });
    }
  });
})(CKEDITOR);
