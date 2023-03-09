import {
  ButtonView,
  DropdownButtonView,
  LabeledFieldView,
  Model,
  addListToDropdown,
  createDropdown,
  createLabeledInputText
} from 'ckeditor5/src/ui';
import { Plugin, icons } from 'ckeditor5/src/core';
import { Collection } from 'ckeditor5/src/utils';
import initializeAutocomplete from "../../../../../../contrib/linkit/js/ckeditor5_plugins/linkit/src/autocomplete";

class WebAreaLinkit extends Plugin {

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'WebAreaLinkit';
  }

  init() {
    const editor = this.editor;
    this.locale = editor.locale;
    this.options = editor.config.get('linkit');
    this.options = {...this.options, autocompleteUrl: "/linkit/autocomplete/web_area_content" };
    this._extendLinkUITemplate();
    this._handleExtraFormFieldSubmit();
    this._handleDataLoadingIntoExtraFormField();
  }

  _extendLinkUITemplate() {
    const editor = this.editor;
    // Create a new dropdown element we'll use for switching linkit profiles.
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

    // Brought all this over from the Linkit plugin.
    const linkFormView = editor.plugins.get( 'LinkUI' ).formView;
    let wasAutocompleteAdded = false;

    editor.plugins.get( 'ContextualBalloon' )._rotatorView.content.on('add', ( evt, view ) => {
      if (view !== linkFormView || wasAutocompleteAdded) {
        return;
      }

      // Create a new input field that we'll turn into a linkit autocomplete field.
      const newUrlField = this._createUrlInput();
      // Add the dropdown as the first element of the LinkFormView.
      linkFormView.children.add(dropdownView, 0);
      linkFormView.children.add(newUrlField, 1);

      linkFormView.on( 'render', () => {
        linkFormView._focusables.add( dropdownView, 1 );
        linkFormView.focusTracker.add( dropdownView.element );
        linkFormView._focusables.add( newUrlField, 1 );
        linkFormView.focusTracker.add( newUrlField.element );
      } );

      /**
       * Used to know if a selection was made from the autocomplete results.
       *
       * @type {boolean}
       */
      let selected;

      // Stolen directly from linkit.
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
        },
      );

      wasAutocompleteAdded = true;
      newUrlField.fieldView.template.attributes.class.push('form-linkit-autocomplete');
      // Initially hide our new field.
      newUrlField.element.style.display = 'none';

      // Listen for changes to the dropdown.
      // Show/hide one of the URL fields based on chosen element.
      this.listenTo( dropdownView, 'execute', (evt) => {
        // evt.source is everything that's in our model and additional items.
        if (evt.source.linkitAll === true) {
          // Show the "No filter: all WebCMS content".
          linkFormView.urlInputView.element.style.display = 'block';
          newUrlField.element.style.display = 'none';
        }
        else {
          // Show the "Your internal links".
          linkFormView.urlInputView.element.style.display = 'none';
          newUrlField.element.style.display = 'block';
        }

        // Set the shown option as the dropdown's label.
        dropdownView.buttonView.label = evt.source.label;
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
      model: new Model( {
        withText: true,
        label: this.locale.t('Your internal links'),
        tooltip: this.locale.t('Search within your web areas only.'),
        linkitAll: false,
      } )
    } );

    items.add( {
      type: 'button',
      model: new Model( {
        withText: true,
        label: this.locale.t('No filter: all WebCMS content'),
        tooltip: this.locale.t('Search within your web areas and www.epa.gov.'),
        linkitAll: true,
      } )
    } );

    return items;

  }

  /**
   * Creates a button and stores it in the editor component factory.
   *
   * @param {Drupal.CKEditor5~DrupalElementStyle} buttonConfig
   *   The button configuration.
   * @param {string} group
   *   The name of the group (e.g. 'align', 'viewMode').
   * @param {Drupal.CKEditor5~DrupalElementStyleDefinition[]} definedStyles
   *   A list of defined styles of one group.
   *
   * @see module:ui/componentfactory~ComponentFactory
   *
   * @private
   */
  _createButton(buttonConfig, group, definedStyles) {
    const buttonName = buttonConfig.name;
    const command = this.editor.commands.get('drupalElementStyle');
    const view = new ButtonView(this.locale);

    view.set({
      label: buttonConfig.title,
      icon: buttonConfig.icon,
      tooltip: true,
      isToggleable: true,
    });

    view.bind('isEnabled').to(command, 'isEnabled');
    view.bind('isOn').to(command, 'value', (value) => {
      return value && value[group] === buttonName;
    });

    view.on('execute', this._executeCommand.bind(this, buttonName, group));

    return view;
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
      // injects the argument (here below 👇).
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
