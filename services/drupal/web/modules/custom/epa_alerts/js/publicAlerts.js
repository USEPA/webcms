(function ($) {
  Drupal.behaviors.publicEpaAlerts = {
    attach: function (context, settings) {
      $.ajax({
      url: '/views/ajax',
      data: {
        view_name: 'public_alerts',
        view_display_id: 'default',
        view_dom_id: 'js-view-dom-id-public_alerts_default',
      },
      success: function (response) {
        var results = $.grep(response, function(obj){
          return obj.method === "replaceWith";
        });
        if (results) {
          var viewHtml = results[0].data;
          $('.js-view-dom-id-epa-alerts--public').html(viewHtml);
        }
      }
      });
    }
  };
})(jQuery);

