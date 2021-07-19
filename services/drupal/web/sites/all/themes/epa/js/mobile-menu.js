(function ($) {

// Convert main menu into a mobile menu.
Drupal.behaviors.mobileMenu = {
  attach: function (context) {

    // Create mobile menu container, create mobile bar, and clone the main menu.
    var $mobileNav = $('<div id="mobile-nav" class="mobile-nav"></div>'),
        $mobileBar = $('<div class="mobile-bar clearfix"><a class="menu-button" href="#mobile-links">Menu</a></div>'),
        $mobileLinks = $('<div id="mobile-links" class="mobile-links element-hidden"></div>'),
        $newMenu = $('.main-nav', context).find('> .nav__inner > .menu').clone();

    // Reset menu list class and remove second level menu items.
    $newMenu.attr('class', 'menu').find('ul').each(function() {
      $(this).attr('class', 'menu sub-menu');
    });
    $newMenu.find('ul').remove();

    // Insert the cloned menus into the mobile menu container.
    $newMenu.appendTo($mobileLinks);

    // Insert the top bar into mobile menu container.
    $mobileBar.prependTo($mobileNav);

    // Insert the mobile links into mobile menu container.
    $mobileLinks.appendTo($mobileNav);

    // Add mobile menu to the page.
    $('.masthead', context).after($mobileNav);

    // Open/Close mobile menu when menu button is clicked.
    var $mobileMenuWrapper = $('#mobile-nav', context).find('.mobile-links'),
        $mobileMenuLinks = $mobileMenuWrapper.find('a');

    $mobileMenuLinks.attr('tabindex', -1);
    $('.mobile-bar .menu-button', context).click(function(e) {
      $(this).toggleClass('menu-button-active');
      $mobileMenuWrapper.toggleClass('element-hidden');
      // Take mobile menu links out of tab flow if hidden.
      if ($mobileMenuWrapper.hasClass('element-hidden')) {
        $mobileMenuLinks.attr('tabindex', -1);
      }
      else {
        $mobileMenuLinks.removeAttr('tabindex');
      }
      e.preventDefault();
    });

    // Set the height of the menu.
    $mobileMenuWrapper.height($(document).height());
  }
};

})(jQuery);
