/**
 * CKEditor overrides.
 */

CKEDITOR.on('dialogDefinition', function(ev) {
  var dialogName = ev.data.name;
  var dialogDefinition = ev.data.definition;

  if(dialogName == 'table') {
    var info = dialogDefinition.getContents('info');
    // Change default width to empty.
    info.get('txtWidth')['default'] = '';

    // Change default headers to First Row.
    info.get('selHeaders')['default'] = 'row';
  }
});
