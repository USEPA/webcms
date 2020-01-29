(function ($) {
  Drupal.behaviors.internalEpaAlerts = {
    attach: function (context, settings) {

      $.ajax({
      url: '/views/ajax',
      type: 'post',
      data: {
        view_name: 'internal_alerts',
        view_display_id: 'default',
      },
      dataType: 'json',
      success: function (response) {
        var results = $.grep(response, function(obj){
          return obj.method === "replaceWith";
        });
        if (results) {
          var viewHtml = results[0].data;
          $('.js-view-dom-id-epa-alerts--internal').html(viewHtml);
        }
      }
      });
    }
  };
})(jQuery);

