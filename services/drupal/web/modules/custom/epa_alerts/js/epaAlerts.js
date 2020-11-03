(function ($, Drupal) {

  Drupal.behaviors.epaAlerts = {
    attach: function (context, settings) {
      $('body', context).once('loadEpaAlerts').each(function() {
        // Context is set in the custom blocks. The two options are:
        // 1. internal
        // 2. public
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

        getAlerts.commands.insert = function (ajax, response, status) {
          if (response.selector == '.js-view-dom-id-js-view-dom-id-' + alertContext + '_alerts_default') {
            var responseHTML = $.parseHTML(response.data);
            var noResults = $(responseHTML).find('.view__empty').length > 0 ? true : false;

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

        getAlerts.commands.destroyObject = function (ajax, response, status) {
          Drupal.ajax.instances[this.instanceIndex] = null;
        }

        getAlerts.execute();
      });
    }
  };
})(jQuery, Drupal);
