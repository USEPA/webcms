// Media embed with link
import Drupal from 'drupal';

(function(Drupal) {
  Drupal.behaviors.vidCopy = {
    attach(context) {
      const vidCopyButton = context.querySelector('.field--vid-button');

      vidCopyButton.addEventListener('click', function(e) {
        e.preventDefault();
        navigator.clipboard.writeText(window.location.href);
      });
    },
  };
})(Drupal);
