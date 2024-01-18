// Sidenav menu script
import Drupal from 'drupal';

(function(Drupal) {
  Drupal.behaviors.sidenavMenu = {
    attach(context) {
      once('sidenav-menu', 'html').forEach(() => {
        const pageBody = document.body;
        const sideNavMenu = context.querySelector('.menu--sidenav-nav');
        const sideNavOverlay = context.querySelector('.menu-sidenav__overlay');
        const sideNavTrigger = context.querySelector('.web-area-menu__button');

        function toggleVisiblity() {
          pageBody.classList.toggle('menu-sidenav--active');
          sideNavMenu.classList.toggle('is-visible');
          sideNavTrigger.classList.toggle('is-open');
          sideNavOverlay.classList.toggle('is-visible');
        }

        [sideNavOverlay, sideNavTrigger].forEach(elem => {
          elem.addEventListener('click', toggleVisiblity);
        });

        const subNavMenus = context.querySelectorAll(
          '.menu--sidenav .menu__subnav'
        );

        subNavMenus.forEach((subNav, index) => {
          const subId = `sub-menu-${index}`;
          const subBtnSib = subNav.previousElementSibling;
          subNav.setAttribute('id', subId);
          subBtnSib.setAttribute('aria-controls', subId);
        });
      });
    },
  };
})(Drupal);
