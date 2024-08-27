/**
 * @file
 * EPA Dropdown
 */

Drupal.behaviors.epaDropdown = {
  attach(context) {
    const dropdownButtons = once('epa-dropdown', '.js-epa-dropdown', context);

    dropdownButtons.forEach(dropdownButton => {
      const dropdownDrawerID = dropdownButton.getAttribute('aria-controls');
      const dropdownDrawer = document.getElementById(dropdownDrawerID);
      const drawerLinks = dropdownDrawer.querySelectorAll('a, button');

      // Close drawer on outside click.
      const handleOutsideClick = event => {
        if (event.target.closest(`#${dropdownDrawerID}`)) return;
        closeDrawer(dropdownDrawer, dropdownButton);
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

      dropdownButton.addEventListener('click', event => {
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
            closeDrawer(dropdownDrawer, dropdownButton);
          }
        });
      };

      // Close drawer on page load.
      closeDrawer(dropdownDrawer, dropdownButton);

      // Trap focus inside drawer.
      handleKeyDown(dropdownDrawer);
    });
  },
};
