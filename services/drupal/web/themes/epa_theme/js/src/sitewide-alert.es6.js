// Sitewide Alert script
import Drupal from 'drupal';

(function(Drupal) {
  Drupal.behaviors.sitewideAlert = {
    attach(context) {
      const alerts = context.querySelectorAll('.js-sitewide-alert');
      const cacheLimitDays = 14;
      const now = Date.now();
      let closedHashes = getClosedAlerts();

      alerts.forEach(alert => {
        const alertHash = alert.dataset.alert;
        const closeButton = alert.querySelector('.js-sitewide-alert__close');

        // Show all but recently closed alerts.
        if (
          !(
            closedHashes[alertHash] !== null &&
            now - cacheLimitDays * 1000 * 60 * 60 * 24 < closedHashes[alertHash]
          )
        ) {
          alert.classList.remove('u-hidden');
        }

        closeButton.addEventListener('click', event => {
          const timestamp = Date.now();
          closedHashes = getClosedAlerts();
          closedHashes[alertHash] = timestamp;

          // Hide alert when close button clicked.
          alert.classList.add('u-hidden');

          // Add alert hash to localStorage.
          setClosedAlerts(closedHashes);
        });
      });

      function getClosedAlerts() {
        let closedHashes = localStorage.getItem('epaClosedAlerts');
        if (closedHashes === null) {
          closedHashes = {};
        } else {
          closedHashes = JSON.parse(closedHashes);
        }
        return closedHashes;
      }

      function setClosedAlerts(closedHashes) {
        return localStorage.setItem(
          'epaClosedAlerts',
          JSON.stringify(closedHashes)
        );
      }
    },
  };
})(Drupal);
