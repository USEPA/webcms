// Sidenav menu script
import Drupal from 'drupal';

(function(Drupal) {
  Drupal.behaviors.sidenavMenu = {
    attach(context) {
      const pageBody = context.querySelector('body');
      const sideNavMenu = context.querySelector('.menu--sidenav-nav');
      const sideNavOverlay = context.querySelector('.menu-sidenav__overlay');
      const sideNavTrigger = context.getElementById('web-area-menu__button');
      const blockWebArea = context.getElementById('block-webareamenu');

      const focusable = Array.from(
        blockWebArea.querySelectorAll('button, [href], input, select, textarea')
      ).filter(item => item.tabIndex !== -1 && item.hidden !== true);
      const numberFocusElements = focusable.length;
      const firstFocusableElement = focusable[0];
      const lastFocusableElement = focusable[numberFocusElements - 1];

      function toggleVisiblity() {
        pageBody.classList.toggle('js-menu-sidenav--active');
        sideNavMenu.classList.toggle('is-visible');
        sideNavOverlay.classList.toggle('is-visible');
        sideNavTrigger.classList.toggle('is-open');
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

      blockWebArea.addEventListener('keydown', event => {
        if (event.key === 'Tab') {
          if (
            event.shiftKey &&
            document.activeElement === firstFocusableElement
          ) {
            event.preventDefault();
            lastFocusableElement.focus();
          } else if (
            document.activeElement === lastFocusableElement &&
            !event.shiftKey
          ) {
            event.preventDefault();
            firstFocusableElement.focus();
          }
        }
      });
    },
  };
})(Drupal);
