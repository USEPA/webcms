(function ($) {

Drupal.behaviors.ieZebra = {
  attach: function(context) {
    $('.lt-ie9 table:not(.nostyle, .nostripe) tr:even', context).addClass('tint');
  }
}

})(jQuery);
