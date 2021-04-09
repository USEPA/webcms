(function(Drupal, $) {
  "use strict";

  Drupal.behaviors.epaNew = {
    attach: function(context) {
      const now = new Date().getTime();

      $("ins[data-date]", context).each(function() {
        const data = $(this).data(),
          offset = 30 * 24 * 60 * 60 * 1000,
          datePieces = data.date.replace(/\,/g, "-").split("-");
        // If we don't have a year, month, and day, we've got some kind of
        // invalid value. Let's assume that's expired.
        if (datePieces.length < 3) {
          $(this).removeClass("new");
          return;
        }
        const year = datePieces[0];
        const month = datePieces[1];
        const day = datePieces[2];
        const expired = new Date(year, month - 1, day - 1).getTime() + offset;
        if (now > expired) {
          $(this).removeClass("new");
        } else {
          $(this).addClass("new"); // Needed for old ins tags that didn't add new by default.
        }
      });
    }
  };
})(Drupal, jQuery);
