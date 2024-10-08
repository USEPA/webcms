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
          document.removeEventListener('keydown', handleKeydown);
        }
      }
    };

    dropdowns.forEach(dropdown => {
      const closeButtons = dropdown.querySelectorAll('.js-epa-dropdown-close');

      if (closeButtons.length > 0) {
        closeButtons.forEach(closeButton => {
          closeButton.addEventListener('click', e => {
            e.preventDefault();
            closeDropdown(dropdown);
          });
        });
      }

      // Add event handlers when opening a dropdown.
      dropdown.addEventListener('toggle', e => {
        if (dropdown.open) {
          document.addEventListener('click', handleOutsideClick);
          document.addEventListener('focusout', handleOutsideFocus, false);
          document.addEventListener('keydown', handleKeydown(dropdown));
        }
      });

      // Function to handle keydowns while drawer is open.
      const handleKeydown = element => {
        element.addEventListener('keydown', e => {
          if (e.key === 'Escape') {
            // Close drawer on escape key press.
            e.preventDefault();
            closeDropdown(dropdown);
          }
        });
      };
    });
  },
};
