(function (Drupal, CKEDITOR) {
  CKEDITOR.plugins.add('drupalinlinemedia', {
    requires: 'drupalmedia',
    icons: 'drupalinlinemedia',
    beforeInit: function beforeInit(editor) {
      
      var dtd = CKEDITOR.dtd;
      dtd['drupal-inline-media'] = { '#': 1 };

      dtd.a['drupal-inline-media'] = 1;
      dtd.p['drupal-inline-media'] = 1;

      drupalmediawidget = editor.widgets.registered.drupalmedia;

      editor.widgets.add('drupalinlinemedia', CKEDITOR.tools.extend({
        upcast: function upcast(element, data) {
          var attributes = element.attributes;
          if (element.name !== 'drupal-inline-media' || attributes['data-entity-type'] !== 'media' || attributes['data-entity-uuid'] === undefined) {
            return;
          }
          data.attributes = CKEDITOR.tools.copy(attributes);
          data.hasCaption = data.attributes.hasOwnProperty('data-caption');
          data.isInline = true;

          if (data.hasCaption && data.attributes['data-caption'] === '') {
            data.attributes['data-caption'] = ' ';
          }
          data.label = null;
          data.link = null;
          if (element.parent.name === 'a') {
            data.link = CKEDITOR.tools.copy(element.parent.attributes);

            Object.keys(element.parent.attributes).forEach(function (attrName) {
              if (attrName.indexOf('data-cke-') !== -1) {
                delete data.link[attrName];
              }
            });
          }

          var hostEntityLangcode = document.getElementById(editor.name).getAttribute('data-media-embed-host-entity-langcode');
          if (hostEntityLangcode) {
            data.hostEntityLangcode = hostEntityLangcode;
          }
          return element;
        },
        downcast: function downcast() {
          var downcastElement = new CKEDITOR.htmlParser.element('drupal-inline-media', this.data.attributes);
          if (this.data.link) {
            var link = new CKEDITOR.htmlParser.element('a', this.data.link);
            link.add(downcastElement);
            return link;
          }
          return downcastElement;
        }
      }, drupalmediawidget));

      editor.addCommand('drupalinlinemedia', {
        allowedContent: {
          'drupal-inline-media': {
            attributes: {
              '!data-entity-type': true,
              '!data-entity-uuid': true,
              '!data-view-mode': true,
              '!data-align': true,
              '!data-caption': true,
              '!alt': true,
              '!title': true
            },
            classes: {}
          }
        },

        requiredContent: new CKEDITOR.style({
          element: 'drupal-inline-media',
          attributes: {
            'data-entity-type': '',
            'data-entity-uuid': ''
          }
        }),
        modes: { wysiwyg: 1 },

        canUndo: true,
        exec: function exec(editor) {
          var saveCallback = function saveCallback(values) {
            editor.fire('saveSnapshot');
            var mediaElement = editor.document.createElement('drupal-inline-media');

            var attributes = values.attributes;
            Object.keys(attributes).forEach(function (key) {
              mediaElement.setAttribute(key, attributes[key]);
            });
            editor.insertHtml(mediaElement.getOuterHtml());
            editor.fire('saveSnapshot');
          };

          Drupal.ckeditor.openDialog(editor, editor.config.DrupalInlineMediaLibrary_url, {}, saveCallback, editor.config.DrupalInlineMediaLibrary_dialogOptions);
        }
      });

      if (editor.ui.addButton) {
        editor.ui.addButton('DrupalInlineMedia', {
          label: Drupal.t('Insert inline media from Media Library'),
          command: 'drupalinlinemedia'
        });
      }
    }
  });
})(Drupal, CKEDITOR);