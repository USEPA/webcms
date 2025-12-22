import { Plugin } from 'ckeditor5/src/core';
import { View } from 'ckeditor5/src/ui';
import initializeAutocomplete from '../../../../../../contrib/linkit/js/ckeditor5_plugins/linkit/src/autocomplete';

/**
 * This plugin provides a dropdown that lets users select between different
 * linkit matchers. We have two linkit matchers currently; the default which
 * allows filtering to all content and  the second one filtering results to only
 * nodes in Web Areas the user belongs to.
 */ 

export default class WebAreaLinkit extends Plugin {
  static get pluginName() {
    return 'WebAreaLinkit';
  }

  init() {
    const editor = this.editor;

    // Ensure LinkUI exists.
    const linkUI = editor.plugins.get('LinkUI');
    const balloon = editor.plugins.get('ContextualBalloon');

    balloon.on('set:visibleView', (evt, name, newView) => {
      if (newView !== linkUI.formView) {
        return;
      }

      this._ensureProfileRadios(linkUI.formView);
    });
  }

  _ensureProfileRadios(formView) {
    if (formView._webAreaProfileRadios) {
      return;
    }

    const locale = this.editor.locale;
    const radiosView = new View(locale);

    radiosView.setTemplate({
      tag: 'div',
      attributes: {
        class: ['ck-webarea-linkit-profiles'],
        style: {
          marginBottom: '8px',
        },
      },
      children: [
        this._radio(locale, 'default', 'No filter: all WebCMS content', true),
        this._radio(locale, 'web_area_content', 'Your internal links'),
      ],
    });

    // Insert above URL field.
    formView.children.add(radiosView, 0);
    formView._focusables.add(radiosView);
    formView.focusTracker.add(radiosView.element);

    // Wire up behavior.
    radiosView.element.addEventListener('change', (e) => {
      if (!e.target.matches('input[type="radio"]')) {
        return;
      }
      this._switchProfile(e.target.value, formView);
    });

    // Initialize default profile.
    this._switchProfile('default', formView);

    formView._webAreaProfileRadios = radiosView;
  }

  _radio(locale, value, label, checked = false) {
    const view = new View(locale);

    view.setTemplate({
      tag: 'label',
      attributes: {
        style: {
          display: 'block',
          fontSize: '13px',
        },
      },
      children: [
        {
          tag: 'input',
          attributes: {
            type: 'radio',
            name: 'webarea-linkit-profile',
            value,
            checked,
          },
        },
        {
          tag: 'span',
          children: [label],
        },
      ],
    });

    return view;
  }

  _switchProfile(profile, formView) {
    const input = formView.urlInputView.fieldView.element;

    // Remove any existing autocomplete.
    input.removeAttribute('data-autocomplete-path');

    const autocompleteUrl = `/linkit/autocomplete/${profile}`;

    initializeAutocomplete(input, {
      autocompleteUrl,
      selectHandler(event, { item }) {
        if (item.path) {
          event.target.value = item.path;
        }
        return false;
      },
    });
  }
}
