// Eternal Links script
import Drupal from 'drupal';

(function(Drupal) {
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
      const externalLinks = context.querySelectorAll(
        "a:not([href=''], [href^='#'], [href^='?'], [href^='/'], [href^='.'], [href^='javascript:'], [href^='mailto:'], [href^='tel:'])"
      );

      externalLinks.forEach(function(el) {
        if (el.hasAttribute('href') && linkIsProtected(el)) {
          const translatedAccessible = Drupal.t('Link to protected area');
          el.insertAdjacentHTML(
            'beforeend',
            `<span class="protected-link__tag" title="${translatedAccessible}"><svg class="usa-icon" aria-hidden="true" focusable="false" role="img"><use href="/themes/epa_theme/images/sprite.svg#lock"></use></svg></span>`
          );
        }
      });
    },
  };
})(Drupal);
