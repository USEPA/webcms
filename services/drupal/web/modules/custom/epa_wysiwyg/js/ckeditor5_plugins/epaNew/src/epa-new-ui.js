import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';
import icon from '../../../../icons/sparkles.svg';

export default class EPANewUi extends Plugin {
  init() {
    const editor = this.editor;

    // This will register the simpleBox toolbar button.
    editor.ui.componentFactory.add('epaNew', (locale) => {
      const command = editor.commands.get('insertEPANewTag');
      const buttonView = new ButtonView(locale);

      // Create the toolbar button.
      buttonView.set({
        label: editor.t('New!'),
        icon,
        tooltip: true,
        withText: true,
      });

      // Bind the state of the button to the command.
      buttonView.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');

      // Execute the command when the button is clicked (executed).
      this.listenTo(buttonView, 'execute', () =>
        editor.execute('insertEPANewTag'),
      );

      return buttonView;
    });
  }
}
