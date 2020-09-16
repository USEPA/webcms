// Toggle Admin script
import Drupal from 'drupal';

(function(Drupal) {
  Drupal.behaviors.toggleAdmin = {
    attach(context) {
      const toggleButton = context.querySelector('.js-toggle-admin');
      const adminContent = context.querySelectorAll(
        '.usa-alert, .button-group--base[aria-label="Primary tasks"], #content-moderation-entity-moderation-form'
      );

      if (toggleButton !== null) {
        toggleButton.addEventListener('click', event => {
          // Toggle button text when clicked.
          if (toggleButton.innerHTML === 'Hide Admin Info') {
            toggleButton.innerHTML = 'Show Admin Info';
          } else {
            toggleButton.innerHTML = 'Hide Admin Info';
          }

          // Show/hide admin content when toggle button clicked.
          adminContent.forEach(content => {
            content.classList.toggle('u-hidden');
          });
        });
      }
    },
  };
})(Drupal);
