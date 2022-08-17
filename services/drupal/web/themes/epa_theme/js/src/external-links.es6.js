// External Links script
import Drupal from 'drupal';

(function(Drupal) {
  Drupal.behaviors.externalLinks = {
    attach(context, settings) {
      const allowedDomains = [
        'airknowledge.gov',
        'airnow.gov',
        'clu-in.org',
        'energystar.gov',
        'relocatefeds.gov',
        'urbanwaterpartners.gov',
        'urbanwaters.gov',
        'westcoastcollaborative.org',
        'usepa.sharepoint.com',
      ];
      function linkIsExternal(linkElement) {
        let external = true;

        if (
          linkElement.host === 'epa.gov' ||
          linkElement.host === 'www.epa.gov' ||
          linkElement.host.endsWith('.epa.gov') ||
          linkElement.host === window.location.host
        ) {
          external = false;
        }

        allowedDomains.forEach(domain => {
          if (
            linkElement.host === domain ||
            linkElement.host.endsWith(`.${domain}`)
          ) {
            external = false;
          }
        });

        return external;
      }
      const externalLinks = context.querySelectorAll(
        "a:not([href=''], [href^='#'], [href^='?'], [href^='/'], [href^='.'], [href^='javascript:'], [href^='mailto:'], [href^='tel:'])"
      );
      const translate = {
        en: ['Exit', 'Exit EPA website'],
        es: ['Salir', 'Salir del sitio web de la EPA'],
        ar: ['خروج', 'الخروج من موقع وكالة حماية البيئة'],
        zh_CN: ['退出', '退出环保署网页'],
        zh_TW: ['退出', '退出環保署網頁'],
        bn: ['বাহির', 'ইপিএ এর ওয়েবসাইট থেকে বাইরে যান'],
        de: ['Verlassen', 'EPA-Website verlassen'],
        fr: ['Quitter', 'Quitter le site de l’EPA'],
        gu: ['બહાર નીકળો', 'EPAની વેબસાઇટમાંથી બહાર નીકળો'],
        ht: ['Sòti', 'Sòti sou sit entènèt EPA a'],
        it: ['Exit', 'Exit EPA Website'],
        ko: ['출구', 'EPA 웹사이트 종료'],
        pt: ['Sair', 'Sair do site da EPA'],
        ru: ['Покинуть', 'Покинуть веб сайт EPA'],
        tl: ['Lumabas', 'Lumabas sa EPA website'],
        vi: ['Thoát ra', 'Thoát ra khỏi trang web EPA'],
      };
      externalLinks.forEach(function(el) {
        if (el.hasAttribute('href') && linkIsExternal(el)) {
          let translatedAccessible = Drupal.t('Exit EPA Website');
          const article = el.closest('article[lang]');
          if (article) {
            let lang = article.getAttribute('lang');
            if (!(lang in translate)) {
              lang = 'en';
            }
            translatedAccessible = translate[lang][1];
          }
          el.insertAdjacentHTML(
            'beforeend',
            `<svg class="icon icon--exit is-spaced-before" role="img"><title>${translatedAccessible}</title><use href="/themes/epa_theme/images/sprite.svg#launch"></use></svg>`
          );
        }
      });
    },
  };
})(Drupal);
