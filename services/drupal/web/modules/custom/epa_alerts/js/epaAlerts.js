(function ($, Drupal) {

  Drupal.behaviors.epaAlerts = {
    attach: function (context, settings) {
      // Context is set in the custom blocks. The two options are:
      // 1. internal
      // 2. public
      var context = drupalSettings.epaAlerts.context;

      var viewInfo = {
        view_name: `${context}_alerts`,
        view_display_id: 'default',
        view_dom_id: `js-view-dom-id-${context}_alerts_default`,
      };

      var ajaxSettings = {
        submit: viewInfo,
        url: '/views/ajax',
      };

      var getAlerts = Drupal.ajax(ajaxSettings);

      getAlerts.commands.insert = function(ajax, response, status) {
        $(`.js-view-dom-id-epa-alerts--${context}`).html(response.data);
      };

      getAlerts.commands.destroyObject = function (ajax, response, status) {
        Drupal.ajax.instances[this.instanceIndex] = null;
      }

      getAlerts.execute();
    }
  };
})(jQuery, Drupal);

