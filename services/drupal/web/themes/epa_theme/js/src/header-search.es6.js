// Header Search script
import Drupal from 'drupal';

(function (Drupal) {
  Drupal.behaviors.headerSearch = {
    attach(context, settings) {
      const BREAKPOINT = '(max-width: calc(55em - 1px))';
      const PROCESSED_ATTRIBUTE = 'header-search-processed';

      once('header-search', '.js-header-search', context).forEach(header => {
        const button = header.querySelector(
          '[aria-controls="header-search-drawer"]'
        );
        const drawer = header.querySelector('#header-search-drawer');

        if (!button || !drawer) {
          return;
        }

        const mediaQuery = window.matchMedia(BREAKPOINT);

        // Open/close the drawer when button is clicked.
        function toggleDrawer(event) {
          event.preventDefault();
          drawer.toggleAttribute('hidden');
          button.setAttribute(
            'aria-label',
            button.getAttribute('aria-expanded') === 'true'
              ? Drupal.t('Open search drawer')
              : Drupal.t('Close search drawer')
          );
          button.setAttribute(
            'aria-expanded',
            button.getAttribute('aria-expanded') === 'true' ? 'false' : 'true'
          );
        }

        const init = () => {
          drawer.setAttribute('hidden', '');
          button.addEventListener('click', toggleDrawer);
          button.setAttribute('aria-expanded', 'false');
          button.setAttribute('aria-label', Drupal.t('Open search drawer'));

          // Add processed attribute.
          header.setAttribute(PROCESSED_ATTRIBUTE, '');
        };

        const destroy = () => {
          drawer.removeAttribute('hidden');
          button.removeEventListener('click', toggleDrawer);
          button.setAttribute('aria-expanded', 'false');
          button.setAttribute('aria-label', Drupal.t('Open search drawer'));

          // Remove processed attribute.
          header.removeAttribute(PROCESSED_ATTRIBUTE);
        };

        // Only show/hide drawer on small screens.
        if (mediaQuery.matches) {
          init();
        }

        mediaQuery.onchange = e => {
          const isProcessed = header.hasAttribute(PROCESSED_ATTRIBUTE);
          if (e.matches) {
            if (!isProcessed) {
              init();
            }
          } else if (isProcessed) {
            destroy();
          }
        };

        // Initially hide the drawer.
        // drawer.classList.add('u-hidden');

        // Open/close the drawer when button is clicked.
        //button.addEventListener('click', event => {
        //  event.preventDefault();
        //  drawer.toggleAttribute('hidden');
        //  button.setAttribute(
        //    'aria-expanded',
        //    button.getAttribute('aria-expanded') === 'true' ? 'false' : 'true'
        //  );
        //});
      });
    },
  };
})(Drupal);
