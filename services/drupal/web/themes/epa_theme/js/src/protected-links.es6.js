// Protected Links script
import Drupal from 'drupal';

(function (Drupal) {
  Drupal.behaviors.protectedLinks = {
    attach(context, settings) {
      const allowedDomains = [
        'work.epa.gov',
        'intranet.epa.gov',
        'usepa.sharepoint.com',
      ];
      function linkIsProtected(linkElement) {
        let external = false;

        allowedDomains.forEach(domain => {
          if (
            linkElement.host === domain ||
            linkElement.host.endsWith(`.${domain}`)
          ) {
            external = true;
          }
        });

        return external;
      }

      const externalLinks = once(
        'protected-links',
        "a:not([href=''], [href^='#'], [href^='?'], [href^='/'], [href^='.'], [href^='javascript:'], [href^='mailto:'], [href^='tel:'])",
        context
      );

      externalLinks.forEach(function (el) {
        if (el.hasAttribute('href') && linkIsProtected(el)) {
          const translatedAccessible = Drupal.t('Exit to EPA’s internal site');
          el.insertAdjacentHTML(
            'beforeend',
            `<svg class="icon icon--exit is-spaced-before" role="img"><title>${translatedAccessible}</title><use href="/themes/epa_theme/images/sprite.svg#lock"></use></svg>`
          );
        }
      });
    },
  };
})(Drupal);
