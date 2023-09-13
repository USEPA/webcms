import { Plugin } from 'ckeditor5/src/core';
import { Widget } from 'ckeditor5/src/widget';
import AttributeCommand from '@ckeditor/ckeditor5-basic-styles/src/attributecommand';


/**
 * CKEditor 5 plugins do not work directly with the DOM. They are defined as
 * plugin-specific data models that are then converted to markup that
 * is inserted in the DOM.
 *
 * CKEditor 5 internally interacts with simpleBox as this model:
 * <simpleBox>
 *    <simpleBoxTitle></simpleBoxTitle>
 *    <simpleBoxDescription></simpleBoxDescription>
 * </simpleBox>
 *
 * Which is converted for the browser/user as this markup
 * <section class="simple-box">
 *   <h2 class="simple-box-title"></h1>
 *   <div class="simple-box-description"></div>
 * </section>
 *
 * This file has the logic for defining the simpleBox model, and for how it is
 * converted to standard DOM markup.
 */
const EPANEW = 'epaNew';

export default class EpaNewEditing extends Plugin {
  static get requires() {
    return [Widget];
  }

  init() {
    this._defineSchema();
    this._defineConverters();
    this.editor.commands.add(
      'insertEPANewTag',
      new AttributeCommand(this.editor, EPANEW),
    );
  }

  /*
   * This registers the structure that will be seen by CKEditor 5 as
   * <epaNew></epaNew>
   *
   * The logic in _defineConverters() will determine how this is converted to
   * markup.
   */
  _defineSchema() {
    // Schemas are registered via the central `editor` object.
    const schema = this.editor.model.schema;

    // We effectively want this to be treated like the "Bold" or "Italic" button.
    // Following how the bold element is implemented leads to a bit simpler
    // structure.
    schema.extend('$text', { allowAttributes: EPANEW } );
    schema.setAttributeProperties( EPANEW, {
      isFormatting: true,
      copyOnEnter: true
    } );

  }

  /**
   * Converters determine how CKEditor 5 models are converted into markup and
   * vice-versa.
   */
  _defineConverters() {
    // Converters are registered via the central editor object.
    const { conversion } = this.editor;

    const  d = new Date();
    let month = d.getMonth() + 1;
    let day = d.getDate();
    let year = d.getFullYear();
    let date = year + "," + month + "," + day;

    // Upcast Converters: determine how existing HTML is interpreted by the
    // editor. These trigger when an editor instance loads.
    //
    // If <ins class="new"> is present in the existing markup
    // processed by CKEditor, then CKEditor recognizes and loads it as a
    // <epaNew> model.
    conversion.for('upcast').elementToElement({
      model: EPANEW,
      view: {
        name: 'ins',
        classes: 'new',
      },
    });

    conversion.attributeToElement( {
      model: EPANEW,
      view: {
        name: 'ins',
        classes: 'new',
        attributes: {
          'data-date': date
        }
      }
    } );
  }
}
