// Toggle Admin script
import Drupal from 'drupal';

(function (Drupal) {
  Drupal.behaviors.toggleAdmin = {
    attach(context) {
      const HIDE_TEXT = Drupal.t('Hide Admin Info');
      const SHOW_TEXT = Drupal.t('Show Admin Info');
      const [toggleAdmin] = once('toggle-admin', '.js-toggle-admin', context);
      const adminContent = context.querySelectorAll(
        '.epa-content-info-box, .epa-node-tabs, .js-toggle-admin-content, .toolbar'
      );

      if (!toggleAdmin || !adminContent) {
        return;
      }

      const toggleButton = document.createElement('button');
      toggleButton.classList.add('c-toggle-admin');
      toggleButton.innerHTML = HIDE_TEXT;

      const html = document.querySelector('html');
      const body = document.querySelector('body');
      let htmlScrollPaddingTop = html.style.scrollPaddingTop;
      let bodyPaddingTop = body.style.paddingTop;

      toggleButton.addEventListener('click', event => {
        // Toggle button text when clicked.
        if (toggleButton.innerHTML === HIDE_TEXT) {
          htmlScrollPaddingTop = html.style.scrollPaddingTop;
          bodyPaddingTop = body.style.paddingTop;
          toggleButton.innerHTML = SHOW_TEXT;
          body.style.paddingTop = null;
          html.style.scrollPaddingTop = null;
        } else {
          toggleButton.innerHTML = HIDE_TEXT;
          body.style.paddingTop = bodyPaddingTop;
          html.style.scrollPaddingTop = htmlScrollPaddingTop;
        }

        // Show/hide admin content when toggle button clicked.
        adminContent.forEach(content => {
          content.classList.toggle('u-hidden');
        });
      });

      toggleAdmin.replaceWith(toggleButton);
    },
  };
})(Drupal);
