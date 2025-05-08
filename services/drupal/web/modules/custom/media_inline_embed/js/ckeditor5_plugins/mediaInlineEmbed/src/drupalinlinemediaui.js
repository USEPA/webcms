import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';
import icon from '../../../../icons/code.svg';

export default class DrupalInlineMediaUI extends Plugin {
  init() {
    const editor = this.editor;
    const options = this.editor.config.get('drupalMedia');
    const inlineOptions = this.editor.config.get('drupalInlineMedia');
    if (!options || !inlineOptions) {
      return;
    }

    const { libraryURL, openDialog, dialogSettings = {} } = options;
    if (!libraryURL || typeof openDialog !== 'function') {
      return;
    }

    // This will register the simpleBox toolbar button.
    editor.ui.componentFactory.add('drupalInlineMedia', (locale) => {
      const command = editor.commands.get('insertDrupalInlineMedia');
      const buttonView = new ButtonView(locale);

      // Create the toolbar button.
      buttonView.set({
        label: editor.t('Insert inline media from Media Library'),
        icon,
        tooltip: true,
      });

      buttonView.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');
      this.listenTo(buttonView, 'execute', () => {
        openDialog(
          inlineOptions.libraryURL,
          ({ attributes }) => {
            editor.execute('insertDrupalInlineMedia', attributes);
          },
          dialogSettings,
        );
      });

      return buttonView;
    });

  }
}
