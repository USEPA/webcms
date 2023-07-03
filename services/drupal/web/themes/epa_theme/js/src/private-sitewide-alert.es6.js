// Sitewide Alert script
import Drupal from 'drupal';

(function(Drupal) {
  Drupal.behaviors.sitewideAlertPrivate = {
    attach(context) {
      const privateMedia = document.getElementsByClassName('js-media-private');

      if (privateMedia.length > 0) {
        const privateMediaAlert = document.getElementsByClassName(
          'usa-site-alert--private'
        );
        const privateMediaAlertCount = document.getElementById(
          'js-private-media-count'
        );

        if (privateMediaAlert.length === 0) {
          return;
        }

        privateMediaAlertCount.innerHTML = privateMedia.length;

        for (let a = 0; a < privateMediaAlert.length; a++) {
          privateMediaAlert[a].style.display = 'block';
        }
      }
    },
  };
})(Drupal);
