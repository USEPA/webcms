(function ($) {

// Accessible drop-down menus
Drupal.behaviors.dropDownMenu = {
  attach: function (context) {

    var $mainMenu = $('.main-nav', context).find('> .nav__inner > .menu'),
        $topItems = $mainMenu.find('> .menu-item'),
        $topLinks = $topItems.find('> .menu-link'),
        $subLinks = $topItems.find('> .menu > .menu-item > .menu-link');

    // Add ARIA roles to menu elements.
    $mainMenu.attr('role', 'menu');
    $topItems.attr('role', 'presentation');
    $topLinks.attr('role', 'menuitem');

    // Add show-menu class when top links are focused.
    $topLinks.focusin(function () {
      $(this).parent().addClass('show-menu');
    });
    $topLinks.focusout(function () {
      $(this).parent().removeClass('show-menu');
    });

    // Add show-menu class when links are focused.
    $subLinks.focusin(function () {
      $(this).parent().parent().parent().addClass('show-menu');
    });
    $subLinks.focusout(function () {
      $(this).parent().parent().parent().removeClass('show-menu');
    });
  }
};

// hoverIntent
Drupal.behaviors.epaHoverIntent = {
  attach: function (context, settings) {
    if ($().hoverIntent) {
      var config = {
        sensitivity: 7, // number = sensitivity threshold (must be 1 or higher)
        interval: 200,   // number = milliseconds of polling interval
        over: Drupal.epa.epaHoverIntentOver,
        timeout: 250,   // number = milliseconds delay before onMouseOut function call
        out: Drupal.epa.epaHoverIntentOut
      };
      $('.main-nav > .nav__inner > .menu > .menu-item').hoverIntent(config);
    }
  }
};

Drupal.epa = Drupal.epa || {};

Drupal.epa.epaHoverIntentOver = function () {
  "use strict";
  $(this).addClass('show-menu');
}

Drupal.epa.epaHoverIntentOut = function() {
  "use strict";
  $(this).removeClass('show-menu');
}

})(jQuery);
