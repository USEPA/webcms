/**
 * @file
 * EPA Dropdown
 */

Drupal.behaviors.epaDropdown = {
  attach(context) {
    const dropdowns = once('epa-dropdown', '.js-epa-dropdown', context);

    // Handler to close dropdown on outside click.
    const handleOutsideClick = e => {
      dropdowns.forEach(dropdown => {
        if (!dropdown.contains(e.target)) {
          closeDropdown(dropdown);
        }
      });
    };

     // Handler to close dropdown on outside focus.
     const handleOutsideFocus = e => {
      dropdowns.forEach(dropdown => {
        if (!dropdown.contains(e.relatedTarget)) {
          closeDropdown(dropdown);
        }
      });
    };

    // Close dropdown and remove outside click handler if no dropdowns are open.
    const closeDropdown = (dropdown) => {
      if (dropdown.hasAttribute('open')) {
        dropdown.removeAttribute('open');

        let hasOpenDropdown = false;
        dropdowns.forEach(dropdown => {
          if (dropdown.hasAttribute('open')) {
            hasOpenDropdown = true;
          }
        });

        if (!hasOpenDropdown) {
          document.removeEventListener('click', handleOutsideClick);
          document.removeEventListener('focusout', handleOutsideFocus);
        }
      }
    };

    dropdowns.forEach(dropdown => {
      const dropdownButton = dropdown.querySelector('summary');

      // Add outside click handler when opening a dropdown.
      dropdown.addEventListener('toggle', e => {
        if (dropdown.open) {
          document.addEventListener('click', handleOutsideClick);
          document.addEventListener('focusout', handleOutsideFocus, false);
          document.addEventListener('keydown', handleKeydown);
        }
      });

      // Function to handle keydowns while drawer is open.
      const handleKeyDown = element => {
        element.addEventListener('keydown', e => {
          if (e.key === 'Escape') {
            // Close drawer on escape key press.
            e.preventDefault();
            closeDropdown(dropdown);
          }
        });
      };

      // Trap focus inside drawer.
      handleKeyDown(dropdown);
    });
  },
};
