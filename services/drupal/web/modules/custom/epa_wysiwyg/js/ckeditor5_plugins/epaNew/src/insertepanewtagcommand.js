/**
 * @file defines InsertSimpleBoxCommand, which is executed when the simpleBox
 * toolbar button is pressed.
 */
// cSpell:ignore simpleboxediting

import { Command } from 'ckeditor5/src/core';

export default class InsertEpaNewTagCommand extends Command {
  execute() {
    const { model } = this.editor;
    const { selection } = model.document;

    model.change((writer) => {
      // Insert <epaNew>*</epaNew> at the current selection position without
      // pasting over what's in the selection.
      const position = selection.getFirstPosition();
      model.insertObject(createEpaNewTag(writer), position, null, {
        setSelection: 'after'
      });
    });
  }

  refresh() {
    const { model } = this.editor;
    const { selection } = model.document;

    // Determine if the cursor (selection) is in a position where adding a the new
    // tag is permitted. This is based on the schema of the model(s)
    // currently containing the cursor.
    const allowedIn = model.schema.findAllowedParent(
      selection.getFirstPosition(),
      'epaNew',
    );

    // If the cursor is not in a location where a new tag can be added, return
    // null so the addition doesn't happen.
    this.isEnabled = allowedIn !== null;
  }
}

function createEpaNewTag(writer) {
  // Return the element to be added to the editor.
  return writer.createElement('epaNew');
}
