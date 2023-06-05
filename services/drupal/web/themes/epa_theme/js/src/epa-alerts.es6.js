/* eslint-disable */
import Drupal from 'drupal';
import jQuery from 'jquery';
window.$ = window.jQuery = jQuery;

console.log('epa alerts in the new file!!');
(function($, Drupal) {
  Drupal.behaviors.epaAlerts = {
    attach(context, settings) {
      const alerts = once('loadEpaAlerts', 'body', context);
      alerts.forEach(alert => {

        var alertContext = drupalSettings.epaAlerts.context;

        var viewInfo = {
          view_name: alertContext + '_alerts',
          view_display_id: 'default',
          view_dom_id: 'js-view-dom-id-' + alertContext + '_alerts_default',
        };

        var ajaxSettings = {
          submit: viewInfo,
          url: '/views/ajax'
        };

        var getAlerts = Drupal.ajax(ajaxSettings);

        getAlerts.commands.insert = function(ajax, response, status) {
          if (response.selector == '.js-view-dom-id-js-view-dom-id-' + alertContext + '_alerts_default') {
            var responseHTML = $.parseHTML(response.data);
            var noResults = $(responseHTML).find('.view__empty').length > 0 ? true : false;
            console.log(responseHTML, 'response HTML');
            console.log(noResults, 'no results');

            if (!noResults) {
              $('.js-view-dom-id-epa-alerts--' + alertContext, context).hide().html(response.data).slideDown();

              // Call Drupal.attachBehaviors() on added content.
              $('.js-view-dom-id-epa-alerts--' + alertContext, context).each(function (index, element) {
                if (element.nodeType === Node.ELEMENT_NODE) {
                  Drupal.attachBehaviors(element);
                }
              });
            }
          }
        };

        getAlerts.commands.destroyObject = function(ajax, response, status) {
          Drupal.ajax.instances[this.instanceIndex] = null;
        };

        getAlerts.execute();
      });
    },
  };
})(jQuery, Drupal);
