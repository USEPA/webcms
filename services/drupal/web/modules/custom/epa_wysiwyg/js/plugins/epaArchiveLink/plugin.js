(function (CKEDITOR) {
  'use strict';

  CKEDITOR.plugins.add('epaArchiveLink', {
    requires: ['button'],

    init: function(editor) {
      editor.addCommand('epaArchiveLinkCommand', {
        exec : function(editor) {
            switch(editor.config.defaultLanguage) {
                case 'es':
                    var linkHtml = '<a class="tag-link" href="https://archive.epa.gov/"><span class="usa-tag">Busque en el Archivo de la EPA</span></a>';
                    break;
                default:
                    var linkHtml = '<a class="tag-link" href="https://archive.epa.gov/"><span class="usa-tag">Search EPA Archive</span></a>';
            }
            editor.insertHtml(linkHtml);
        }
      });

      editor.ui.addButton('epaArchiveLink', {
        label: 'EPA Archive Link',
        command: 'epaArchiveLinkCommand',
        icon: this.path + 'images/epaArchiveLink.png'
      });
    }
  });
})(CKEDITOR);
