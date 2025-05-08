import DrupalInlineMediaEditing from './drupalinlinemediaediting';
import DrupalInlineMediaGeneralHtmlSupport from './drupalinlinemediageneralhtmlsupport'
import DrupalInlineMediaUI from './drupalinlinemediaui';
import DrupalInlineMediaToolbar from "./drupalinlinemediatoolbar";
import { Plugin } from 'ckeditor5/src/core';

export default class DrupalInlineMedia extends Plugin {
  // Note that DrupalInlineMediaEditing and DrupalInlineMediaUI also extend `Plugin`, but these
  // are not seen as individual plugins by CKEditor 5. CKEditor 5 will only
  // discover the plugins explicitly exported in index.js.
  static get requires() {
    return [
      DrupalInlineMediaEditing,
      DrupalInlineMediaGeneralHtmlSupport,
      DrupalInlineMediaUI,
      DrupalInlineMediaToolbar
    ];
  }
}
