(function (Drupal, $) {

  'use strict';

  Drupal.behaviors.epaNew = {
    attach: function (context) {
      var now = new Date();
      now = now.getTime();

      $('ins[data-date]', context).each(function () {
        var data = $(this).data(),
          offset = 30 * 24 * 60 * 60 * 1000,
          date = data.date.replace(/\,/g, '/'), // Replace , with / for IE9.
          expired = Date.parse(date) + offset;
        if (now > expired) {
          $(this).removeClass('new');
        }
        else {
          $(this).addClass('new'); // Needed for old ins tags that didn't add new by default.
        }
      });
    }
  };

})(Drupal, jQuery);
