(function (CKEDITOR) {
  'use strict';
  
  CKEDITOR.plugins.add('epaArchiveLink', {
    requires: ['button'],

    init: function(editor) {
      editor.addCommand('epaArchiveLinkCommand', {
        exec : function(editor) {
            switch(editor.config.defaultLanguage) {
                case 'es':
                    var linkHtml = '<a class="epa-archive-link" title="Archivo de la EPA" href="https://archive.epa.gov/">Busque en el Archivo de la EPA</a>';
                    break;
                default:
                    var linkHtml = '<a class="epa-archive-link" title="EPA\'s Archive" href="https://archive.epa.gov/">Search EPA Archive</a>';
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