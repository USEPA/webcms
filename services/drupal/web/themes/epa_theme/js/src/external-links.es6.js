// Eternal Links script
import Drupal from 'drupal';

(function(Drupal) {
  Drupal.behaviors.externalLinks = {
    attach(context, settings) {
      const externalLinks = context.querySelectorAll(
        "a:not([href=''], [href*='.gov'], [href*='.mil'], [href^='#'], [href^='?'], [href^='/'], [href^='.'], [href^='javascript:'], [href^='mailto:'], [href^='tel:'], [href*='webcms-uploads-dev.s3.amazonaws.com'], [href*='webcms-uploads-stage.s3.amazonaws.com'], [href*='webcms-uploads-prod.s3.amazonaws.com'])"
      );
      const translate = {
        en: 'Exit',
        es: 'Nuevo',
        ar: 'جديد',
        zh_CN: '新',
        zh_TW: '新',
        fr: 'Nouveau',
        ht: 'Nouvo',
        it: ' Nuovo',
        ko: '신규',
        pt: 'Novo',
        ru: 'Новый',
        tl: 'Bago',
        vi: 'Mới',
      };
      externalLinks.forEach(function(el) {
        if (el.hasAttribute('href')) {
          let translated = Drupal.t('Exit');
          const article = el.closest('article[lang]');
          if (article) {
            const lang = article.getAttribute('lang');
            translated = translate[lang];
          }
          el.insertAdjacentHTML(
            'beforeend',
            `<span class="usa-tag external-link__tag">${translated}</span>`
          );
        }
      });
    },
  };
})(Drupal);
