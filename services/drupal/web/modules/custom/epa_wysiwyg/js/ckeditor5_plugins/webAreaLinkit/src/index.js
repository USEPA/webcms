import {
  DropdownButtonView,
  LabeledFieldView,
  ViewModel,
  addListToDropdown,
  createDropdown,
  createLabeledInputText
} from 'ckeditor5/src/ui';
import { Plugin, icons } from 'ckeditor5/src/core';
import { Collection } from 'ckeditor5/src/utils';
import initializeAutocomplete from "../../../../../../contrib/linkit/js/ckeditor5_plugins/linkit/src/autocomplete";

/**
 * This plugin provides a dropdown that lets users select between different
 * linkit matchers. We have two linkit matchers currently; the default which
 * allows filtering to all content and  the second one filtering results to only
 * nodes in Web Areas the user belongs to.
 *
 * To achieve this we're following a similar pattern to what Linkit takes.
 *
 * We create an autocomplete textfield that is connected to the second
 * "web area only" matcher and the dropdown toggles between showing the default
 * autocomplete or our custom one.
 */
class WebAreaLinkit extends Plugin {

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'WebAreaLinkit';
  }

  init() {
    this.locale = this.editor.locale;
    // @todo: See about passing URL via PHP settings.
    this.options = {...this.editor.config.get('linkit'), autocompleteUrl: "/linkit/autocomplete/web_area_content" };
    // TRICKY: Work-around until the CKEditor team offers a better solution:
    // force the ContextualBalloon to get instantiated early thanks to
    // DrupalImage not yet being optimized like
    // https://github.com/ckeditor/ckeditor5/commit/c276c45a934e4ad7c2a8ccd0bd9a01f6442d4cd3#diff-1753317a1a0b947ca8b66581b533616a5309f6d4236a527b9d21ba03e13a78d8.
    if (this.editor.plugins.get('LinkUI')._createViews) {
      this.editor.plugins.get('LinkUI')._createViews();
    }

    this._extendLinkUITemplate();
    this._handleExtraFormFieldSubmit();
    this._handleDataLoadingIntoExtraFormField();
  }

  _extendLinkUITemplate() {
    const { editor } = this;
    // Brought all this over from the Linkit plugin.
    let wasAutocompleteAdded = false;

    // Copy the same solution from LinkUI as pointed out on
    // https://www.drupal.org/project/drupal/issues/3317769#comment-14985648 and
    // https://git.drupalcode.org/project/drupal/-/merge_requests/2909/diffs?commit_id=cc2cece3be1a9513b02a53d8a6862a6841ef4d5a.
    editor.plugins
      .get('ContextualBalloon')
      .on('set:visibleView', (evt, propertyName, newValue, oldValue) => {
        const linkFormView = editor.plugins.get('LinkUI').formView;
        const linkCommand = editor.commands.get('link');
        if (newValue === oldValue || newValue !== linkFormView) {
          return;
        }

        // Create a new dropdown field that we'll use to let the content author switch between profiles
        if (typeof linkFormView.linkitProfileSelect === 'undefined') {
          const dropdownView = createDropdown( this.locale, DropdownButtonView );
          const itemList = this._buildLinkitProfileList();
          addListToDropdown(dropdownView, itemList);
          dropdownView.buttonView.set( itemList.get(1).model );

          dropdownView.extendTemplate( {
            attributes: {
              class: [
                'ck-epa-web-area-linkit-dropdown'
              ]
            }
          } );

          linkFormView.children.add(dropdownView, 0);
          linkFormView._focusables.add(dropdownView, 0);
          linkFormView.focusTracker.add(dropdownView.element);
          linkFormView.linkitProfileSelect = dropdownView;
        }

        // Create a new input field that we'll turn into a linkit autocomplete field.
        if (typeof linkFormView.myWebAreasLinkField === 'undefined') {
          const newUrlField = this._createUrlInput();
          linkFormView.children.add(newUrlField, 1);
          linkFormView._focusables.add(newUrlField, 1);
          linkFormView.focusTracker.add(newUrlField.element);
          linkFormView.myWebAreasLinkField = newUrlField;

          // Note: Copy & pasted from LinkUI.
          // https://github.com/ckeditor/ckeditor5/blob/f0a093339631b774b2d3422e2a579e27be79bbeb/packages/ckeditor5-link/src/linkui.js#L333-L333
          linkFormView.myWebAreasLinkField.fieldView.element.value =
            linkCommand.myWebAreasLinkField || '';

          // Stolen directly from linkit.
          /**
           * Used to know if a selection was made from the autocomplete results.
           *
           * @type {boolean}
           */
          let selected;

          initializeAutocomplete(
            newUrlField.fieldView.element,
            {
              ...this.options,
              selectHandler: (event, { item }) => {
                if (!item.path) {
                  throw 'Missing path param.' + JSON.stringify(item);
                }

                if (item.entity_type_id || item.entity_uuid || item.substitution_id) {
                  if (!item.entity_type_id || !item.entity_uuid || !item.substitution_id) {
                    throw 'Missing path param.' + JSON.stringify(item);
                  }

                  this.set('entityType', item.entity_type_id);
                  this.set('entityUuid', item.entity_uuid);
                  this.set('entitySubstitution', item.substitution_id);
                }
                else {
                  this.set('entityType', null);
                  this.set('entityUuid', null);
                  this.set('entitySubstitution', null);
                }

                event.target.value = item.path;
                // Also set the value of the other link field.
                linkFormView.urlInputView.fieldView.element.value = item.path;
                selected = true;
                return false;
              },
              openHandler: (event) => {
                selected = false;
              },
              closeHandler: (event) => {
                if (!selected) {
                  this.set('entityType', null);
                  this.set('entityUuid', null);
                  this.set('entitySubstitution', null);
                }
                selected = false;
              },
            }
          );
        }

        // Set the display of the url input to always display first
        linkFormView.urlInputView.element.style.display = 'block';
        // Initially hide our new field.
        linkFormView.myWebAreasLinkField.element.style.display = 'none';

        wasAutocompleteAdded = true;

        // @todo: Figure out cause of why the save and cancel buttons get out of order
        // This is gross, but due to time constraints this works. It seems the
        // second time the init() is called the save and cancel buttons change
        // position in the children array. This bit of logic ensures they are
        // always the last items and placed in the correct positions.
        let saveButton = linkFormView.saveButtonView;
        let cancelButton = linkFormView.cancelButtonView;
        linkFormView.children.remove(saveButton);
        linkFormView.children.remove(cancelButton);
        linkFormView.children.add(saveButton);
        linkFormView.children.add(cancelButton);

        // Listen for changes to the dropdown.
        // Show/hide one of the URL fields based on chosen element.
        this.listenTo( linkFormView.linkitProfileSelect, 'execute', (evt) => {
          // evt.source is everything that's in our model and additional items.
          const linkCommand = editor.commands.get('link');
          const isAll = evt.source.linkitAll === true;

          // Clear values of both inputs
          linkFormView.urlInputView.fieldView.element.value = '';
          linkFormView.myWebAreasLinkField.fieldView.element.value = '';

          // Reset Link command extra data
          this.set('entityType', null);
          this.set('entityUuid', null);
          this.set('entitySubstitution', null);

          // Clear plugin stored path too (used in selectHandler)
          linkCommand.myWebAreasLinkField = '';

          if (isAll) {
            // Show the "No filter: all WebCMS content".
            linkFormView.urlInputView.element.style.display = 'block';
            linkFormView.myWebAreasLinkField.element.style.display = 'none';
          } else {
            // Show the "Your internal links".
            linkFormView.urlInputView.element.style.display = 'none';
            linkFormView.myWebAreasLinkField.element.style.display = 'block';
          }

          // Update dropdown button label
          linkFormView.linkitProfileSelect.buttonView.label = evt.source.label;
        } );
    });
  }

  /**
   * Creates a dropdown element that will let the user choose the linkit profile the autocomplete
   * will run agianst.
   * @private
   */
  _buildLinkitProfileList() {
    const items = new Collection();

    items.add( {
      type: 'button',
      model: new ViewModel( {
        withText: true,
        label: this.locale.t('Your internal links'),
        tooltip: this.locale.t('Search within your web areas only.'),
        linkitAll: false,
      } )
    } );

    items.add( {
      type: 'button',
      model: new ViewModel( {
        withText: true,
        label: this.locale.t('No filter: all WebCMS content'),
        tooltip: this.locale.t('Search within your web areas and www.epa.gov.'),
        linkitAll: true,
      } )
    } );

    return items;

  }

  /**
   * Creates a labeled input view.
   *
   * @private
   * @returns {module:core/ui/labeledfield/labeledfieldview~LabeledFieldView} Labeled field view instance.
   */
  _createUrlInput() {
    const t = this.locale.t;
    const labeledInput = new LabeledFieldView( this.locale, createLabeledInputText );

    labeledInput.label = t( 'Link URL' );

    return labeledInput;
  }

  _handleExtraFormFieldSubmit() {
    const editor = this.editor;
    const linkFormView = editor.plugins.get('LinkUI').formView;
    const linkCommand = editor.commands.get('link');

    this.listenTo(linkFormView, 'submit', () => {
      const values = {
        'data-entity-type': this.entityType,
        'data-entity-uuid': this.entityUuid,
        'data-entity-substitution': this.entitySubstitution,
      };
      // Stop the execution of the link command caused by closing the form.
      // Inject the extra attribute value. The highest priority listener here
      // injects the argument (here below).
      // - The high priority listener in
      //   _addExtraAttributeOnLinkCommandExecute() gets that argument and sets
      //   the extra attribute.
      // - The normal (default) priority listener in ckeditor5-link sets
      //   (creates) the actual link.
      linkCommand.once('execute', (evt, args) => {
        if (args.length < 3) {
          args.push(values);
        } else if (args.length === 3) {
          Object.assign(args[2], values);
        } else {
          throw Error('The link command has more than 3 arguments.')
        }
      }, { priority: 'highest' });
    }, { priority: 'high' });
  }

  _handleDataLoadingIntoExtraFormField() {
    const editor = this.editor;
    const linkCommand = editor.commands.get('link');

    this.bind('entityType').to(linkCommand, 'data-entity-type');
    this.bind('entityUuid').to(linkCommand, 'data-entity-uuid');
    this.bind('entitySubstitution').to(linkCommand, 'data-entity-substitution');
  }
}

export default {
  WebAreaLinkit
};
