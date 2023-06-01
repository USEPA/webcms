import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';
import linkIcon from "../../../../icons/file-pen-solid.svg";
import EditDrupalMediaCommand from "./editdrupalmediacommand";

export default class EpaDrupalMediaUI extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return ['DrupalMediaEditing'];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'EditDrupalMediaUi';
  }

  init() {
    this._createToolbarEditMediaButton();
    this.editor.commands.add(
      'EditDrupalMedia',
      new EditDrupalMediaCommand(this.editor),
    );
  }

  /**
   * Creates a `DrupalLinkMediaUI` button view.
   *
   * Clicking this button shows a {@link module:link/linkui~LinkUI#_balloon}
   * attached to the selection. When an media is already linked, the view shows
   * {@link module:link/linkui~LinkUI#actionsView} or
   * {@link module:link/linkui~LinkUI#formView} if it is not.
   */
  _createToolbarEditMediaButton() {
    const { editor } = this;

    editor.ui.componentFactory.add('EditDrupalMediaButton', (locale) => {
      const button = new ButtonView(locale);
      const linkCommand = editor.commands.get('EditDrupalMedia');

      button.set({
        isEnabled: true,
        label: Drupal.t('Edit Media'),
        icon: linkIcon,
        tooltip: true,
      });

      // Bind button to the command.
      button.bind('isEnabled').to(linkCommand, 'isEnabled');

      this.listenTo(button, 'execute', () => {
        editor.execute('EditDrupalMedia');
      });

      return button;
    });
  }
}
