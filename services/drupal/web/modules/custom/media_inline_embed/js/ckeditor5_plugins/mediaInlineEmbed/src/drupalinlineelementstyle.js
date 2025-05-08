import { Plugin } from 'ckeditor5/src/core';
import DrupalInlineElementStyleUi from './drupalinlineelementstyle/drupalinlineelementstyleui';
import DrupalInlineElementStyleEditing from './drupalinlineelementstyle/drupalinlineelementstyleediting';

export default class DrupalElementStyle extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return [DrupalInlineElementStyleEditing, DrupalInlineElementStyleUi];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'DrupalInlineElementStyle';
  }
}
