(function ($, Drupal) {

// Remove no-js class
Drupal.behaviors.epa = {
  attach: function (context) {
    $('html.no-js', context).removeClass('no-js');
  }
};

// Accessible skiplinks
Drupal.behaviors.skiplinks = {
  attach: function (context) {
    var isWebkit = navigator.userAgent.toLowerCase().indexOf('webkit') > -1,
        isOpera = navigator.userAgent.toLowerCase().indexOf('opera') > -1;

    // Set tabindex on the skiplink targets so IE knows they are focusable, and
    // so Webkit browsers will focus() them.
    $('#main-content, #site-navigation', context).attr('tabindex', -1);

    // Set focus to skiplink targets in Webkit and Opera.
    if (isWebkit || isOpera) {
      $('.skip-links a[href^="#"]', context).click(function() {
        var clickAnchor = '#' + this.href.split('#')[1];
        $(clickAnchor).focus();
      });
    }
  }
};

// Add 'new' class if content is less than 30 days old.
Drupal.behaviors.epaNew = {
  attach: function (context) {
    var now = new Date();
    now = now.getTime();

    $('ins[data-date]', context).each(function () {
      var data = $(this).data(),
        offset = 30 * 24 * 60 * 60 * 1000,
        date = data.date.replace(/\,/g, '/'), // Replace , with / for IE9.
        expired = Date.parse(date) + offset;

      if (now < expired) {
        $(this).addClass('new');
      }
    });
  }
};

// Use jQuery tablesorter plugin.
Drupal.behaviors.tablesorter = {
  attach: function (context) {
    $('table.tablesorter', context).tablesorter();
  }
};

// Add simple accordion behavior.
Drupal.behaviors.accordion = {
  attach: function (context) {
    $('.accordion', context).each(function () {
      var $titles = $(this).find('.accordion-title'),
          $panes = $titles.next('.accordion-pane');
      $panes.addClass('is-closed');
      $titles.each(function () {
        var $target = $(this).next('.accordion-pane');
        $(this).click(function (e) {
          if(!$(this).hasClass('is-active')) {
            $titles.removeClass('is-active');
            $panes.addClass('is-closed');
            $(this).addClass('is-active');
            $target.removeClass('is-closed');
          }
          else {
            $(this).removeClass('is-active');
            $target.addClass('is-closed');
          }
          e.preventDefault();
        });
      });
    });
  }
};

// Move header images before .pane-content.
Drupal.behaviors.headerImages = {
  attach: function (context) {
    $('.box', context).each(function() {
        var $image = $('.image.view-mode-block_header:not(.caption, .block_header-processed)', this).first(),
            $box = $(this);

        // Avoid processing this again in the case of nested boxes.
        $image.addClass("block_header-processed");
        $image.detach();
        $box.prepend($image);
    });
  }
};

Drupal.behaviors.setLoginFocus = {
  attach: function (context) {
    $('body.page-user #edit-name').focus();
  }
};

})(jQuery, Drupal);
