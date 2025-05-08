/* eslint-disable import/no-extraneous-dependencies */
/* cspell:words drupalmediatoolbar */
import { Plugin } from 'ckeditor5/src/core';
import { WidgetToolbarRepository } from 'ckeditor5/src/widget';

import { getClosestSelectedDrupalInlineMediaWidget, isObject } from './utils';

/**
 * Convert dropdown definitions to keys registered in the ComponentFactory.
 *
 * The registration process should be handled by the plugin which handles the UI
 * of a particular feature.
 *
 * @param {Array.<string|Object>} config
 *   The drupalMedia.toolbar configuration.
 *
 * @return {string[]}
 *   A normalized toolbar item list.
 */
function normalizeDeclarativeConfig(config) {
    return config.map((item) => (isObject(item) ? item.name : item));
}

/**
 * @private
 */
export default class DrupalInlineMediaToolbar extends Plugin {
    /**
     * @inheritdoc
     */
    static get requires() {
        return [WidgetToolbarRepository];
    }

    /**
     * @inheritdoc
     */
    static get pluginName() {
        return 'DrupalInlineMediaToolbar';
    }

    /**
     * @inheritdoc
     */
    afterInit() {
        const { editor } = this;
        const widgetToolbarRepository = editor.plugins.get(WidgetToolbarRepository);

        widgetToolbarRepository.register('drupalInlineMedia', {
            ariaLabel: Drupal.t('Drupal Inline Media toolbar'),
            items:
                normalizeDeclarativeConfig(editor.config.get('drupalInlineMedia.toolbar')) ||
                [],
            // Get the selected image or an image containing the figcaption with the selection inside.
            getRelatedElement: (selection) =>
                getClosestSelectedDrupalInlineMediaWidget(selection),
        });
    }
}
