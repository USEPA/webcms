// Media embed with link
import Drupal from 'drupal';

(function(Drupal) {
  Drupal.behaviors.vidCopy = {
    attach(context) {
      const windowHost = window.location.hostname;
      const vidCopy = context.querySelector('.revision-link');

      if (vidCopy !== null) {
        const vidCopyURL = context
          .querySelector('.revision-link')
          .getAttribute('href');
        once('vid-copy', '.js-vid-copy', context).forEach(button => {
          button.addEventListener('click', function(e) {
            e.preventDefault();
            const vidWriteText = `https://${windowHost}${vidCopyURL}`;
            navigator.clipboard.writeText(vidWriteText);
          });
        });
      }
    },
  };
})(Drupal);
