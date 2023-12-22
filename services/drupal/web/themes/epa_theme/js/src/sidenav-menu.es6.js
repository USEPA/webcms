// Sidenav menu script
import Drupal from 'drupal';

(function(Drupal) {
  Drupal.behaviors.sidenavMenu = {
    attach(context) {
      once('sidenav-menu', 'html').forEach(() => {
        const pageBody = document.body;
        const sideNavMenu = context.querySelector('.menu--sidenav-nav');
        const sideNavTrigger = context.querySelector('.web-area-menu__button');

        function toggleVisiblity() {
          pageBody.classList.toggle('usa-js-mobile-nav--active');
          sideNavMenu.classList.toggle('is-visible');
          sideNavTrigger.classList.toggle('is-open');
        }

        sideNavTrigger.addEventListener('click', toggleVisiblity);

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
