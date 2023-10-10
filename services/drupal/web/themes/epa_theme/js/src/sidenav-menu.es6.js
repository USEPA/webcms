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

      const subNavMenus = context.querySelectorAll(
        '.menu--sidenav .menu__subnav'
      );
      let subNavCounter = '1';

      subNavMenus.forEach(subNav => {
        const subId = `sub-menu-${subNavCounter}`;
        const subBtnSib = subNav.previousElementSibling;
        subNav.setAttribute('id', subId);
        subBtnSib.setAttribute('aria-controls', subId);
        subNavCounter++;
      });
    },
  };
})(Drupal);
