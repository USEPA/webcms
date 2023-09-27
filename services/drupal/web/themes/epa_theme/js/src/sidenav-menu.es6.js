// Sidenav menu script
import Drupal from 'drupal';

(function(Drupal) {
  Drupal.behaviors.sidenavMenu = {
    attach(context) {
      const sideNavTrigger = context.getElementById('web-area-menu__button');
      const sideNavMenu = context.querySelector('.menu--sidenav');

      function toggleVisiblity() {
        sideNavMenu.classList.toggle('is-visible');
        sideNavTrigger.classList.toggle('is-open');
      }
      sideNavTrigger.addEventListener('click', toggleVisiblity);
    },
  };
})(Drupal);
