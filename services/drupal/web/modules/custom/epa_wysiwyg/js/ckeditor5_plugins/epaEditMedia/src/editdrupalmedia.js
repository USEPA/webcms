import EpaDrupalMediaUI from "./epadrupalmediaui";
import EpaFileUrlUI from "./epafileurlui";
import { Plugin } from 'ckeditor5/src/core';

export default class EditDrupalMedia extends Plugin {
  // Note that SimpleBoxEditing and SimpleBoxUI also extend `Plugin`, but these
  // are not seen as individual plugins by CKEditor 5. CKEditor 5 will only
  // discover the plugins explicitly exported in index.js.
  static get requires() {
    return [EpaDrupalMediaUI, EpaFileUrlUI];
  }
}
