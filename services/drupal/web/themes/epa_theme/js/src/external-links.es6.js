// Eternal Links script
import Drupal from 'drupal';

(function(Drupal) {
  Drupal.behaviors.externalLinks = {
    attach(context) {
      const externalLinks = context.querySelectorAll(
        "a:not([href=''], [href*='.gov'], [href*='.mil'], [href^='#'], [href^='?'], [href^='/'], [href^='.'], [href^='javascript:'], [href^='mailto:'], [href^='tel:'], [href*='webcms-uploads-dev.s3.amazonaws.com'], [href*='webcms-uploads-stage.s3.amazonaws.com'], [href*='webcms-uploads-prod.s3.amazonaws.com'])"
      );
      externalLinks.forEach(function(el) {
        if (el.hasAttribute('href')) {
          el.insertAdjacentHTML(
            'beforeend',
            `<span class="usa-tag external-link__tag">${Drupal.t(
              'Exit'
            )}</span>`
          );
        }
      });
    },
  };
})(Drupal);
