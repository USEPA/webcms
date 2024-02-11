// Sidenav menu script
import Drupal from 'drupal';

(function(Drupal) {
  Drupal.behaviors.sidenavMenu = {
    attach(context) {
      once('sidenav-menu', 'html').forEach(() => {
        const pageBody = document.body;
        const sideNavMenu = context.querySelector('.menu--sidenav-nav');
        const sideNavTrigger = context.querySelector('.web-area-menu__button');
        const sideNavOverlay = context.querySelector('.menu-sidenav__overlay');
        const blockWebArea = context.getElementById('block-webareamenu');
        const sideNavContact = context.getElementById('menu-sidenav__contact');
        let focusable;
        let numberFocusElements;
        let firstFocusableElement;
        let lastFocusableElement;
        let priorLastElement;

        const sideNavContactClone = sideNavContact.cloneNode(true);
        sideNavContactClone.classList.add('-mobile');
        sideNavMenu.append(sideNavContactClone);

        function toggleVisiblity() {
          pageBody.classList.toggle('menu-sidenav--active');
          sideNavMenu.classList.toggle('is-visible');
          sideNavTrigger.classList.toggle('is-open');
          sideNavOverlay.classList.toggle('is-visible');

          if (!focusable) {
            focusable = Array.from(
              blockWebArea.querySelectorAll(
                'button, [href], input, select, textarea'
              )
            ).filter(item => item.tabIndex !== -1 && item.hidden !== true);
            numberFocusElements = focusable.length;
            firstFocusableElement = focusable[0];
            lastFocusableElement = focusable[numberFocusElements - 1];

            const lastFocusParent = lastFocusableElement.closest('ul');

            if (getComputedStyle(lastFocusParent).display === 'none') {
              priorLastElement = lastFocusableElement;
              lastFocusableElement = lastFocusParent.previousElementSibling;
            }
          }

          if (lastFocusableElement) {
            lastFocusableElement.addEventListener('click', function() {
              const swap = lastFocusableElement;
              lastFocusableElement = priorLastElement;
              priorLastElement = swap;
            });
          }
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
      });
    },
  };
})(Drupal);
