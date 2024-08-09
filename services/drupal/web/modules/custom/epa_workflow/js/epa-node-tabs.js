/**
 * @file
 * EPA Node Tabs
 */

Drupal.behaviors.epaNodeTabs = {
  attach(context) {
    const [nodeTabsButton] = once('epa-node-tabs', '#epa-node-tabs-button', context);

    if (!nodeTabsButton) {
      return;
    }

    const nodeTabsDrawer = document.getElementById(nodeTabsButton.getAttribute('aria-controls'));
    const drawerLinks = nodeTabsDrawer.querySelectorAll('.epa-node-tabs__drawer-link');

    // Close drawer on outside click.
    const handleOutsideClick = event => {
      if (event.target.closest('.epa-node-tabs__drawer')) return;
      closeDrawer(nodeTabsDrawer, nodeTabsButton);
    };

    const openDrawer = (drawer, button) => {
      if (
        button.getAttribute('aria-expanded') === 'false'
      ) {
        button.setAttribute('aria-expanded', 'true');
        drawer.setAttribute('aria-expanded', 'true');
        document.addEventListener('click', handleOutsideClick);
      }
    };

    const closeDrawer = (drawer, button) => {
      if (
        button.getAttribute('aria-expanded') === 'true'
      ) {
        button.setAttribute('aria-expanded', 'false');
        drawer.setAttribute('aria-expanded', 'false');
        document.removeEventListener('click', handleOutsideClick);
      }
    };

    nodeTabsButton.addEventListener('click', event => {
      const isExpanded = event.target.getAttribute('aria-expanded') === 'true';

      if (!isExpanded) {
        openDrawer(
          document.getElementById(event.target.getAttribute('aria-controls')),
          event.target
        );
      } else {
        closeDrawer(
          document.getElementById(event.target.getAttribute('aria-controls')),
          event.target
        );
      }

      event.preventDefault();
      event.stopPropagation();
    });

    // Function to handle key downs while drawer is open.
    const handleKeyDown = element => {
      const firstFocusableElement = drawerLinks[0];
      const lastFocusableElement = drawerLinks[drawerLinks.length - 1];

      element.addEventListener('keydown', e => {
        if (e.key === 'Tab') {
          // If shift key pressed for shift + tab combination
          if (e.shiftKey) {
            if (document.activeElement === firstFocusableElement) {
              // Add focus for the last focusable element
              lastFocusableElement.focus();
              e.preventDefault();
            }
          }
          // If focused has reached to last focusable element then focus first focusable element after pressing tab
          else if (document.activeElement === lastFocusableElement) {
            // Add focus for the first focusable element
            firstFocusableElement.focus();
            e.preventDefault();
          }
        } else if (e.key === 'Escape') {
          // Close drawer on escape key press.
          e.preventDefault();
          closeDrawer(nodeTabsDrawer, nodeTabsButton);
        }
      });
    };

    // Close drawer on page load.
    closeDrawer(nodeTabsDrawer, nodeTabsButton);

    // Trap focus inside drawer.
    handleKeyDown(nodeTabsDrawer);
  },
};
