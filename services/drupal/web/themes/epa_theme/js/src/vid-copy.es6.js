// Media embed with link
import Drupal from 'drupal';

(function(Drupal) {
  Drupal.behaviors.vidCopy = {
    attach(context) {
      const vidCopyButton = context.querySelector('.field--vid-button');
      const windowHost = window.location.hostname;
      const vidCopyURL = context
        .querySelector('.revision-link')
        .getAttribute('href');

      vidCopyButton.addEventListener('click', function(e) {
        e.preventDefault();
        const vidWriteText = `https://${windowHost}${vidCopyURL}`;
        navigator.clipboard.writeText(vidWriteText);
      });
    },
  };
})(Drupal);
