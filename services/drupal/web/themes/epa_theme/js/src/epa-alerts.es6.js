/* global drupalSettings:true */
import Drupal from 'drupal';

(function (Drupal) {
  const slideDown = (target, duration = 400) => {
    target.style.removeProperty('display');
    let display = window.getComputedStyle(target).display;

    if (display === 'none') {
      display = 'block';
    }

    target.style.display = display;
    const height = target.offsetHeight;
    target.style.overflow = 'hidden';
    target.style.height = 0;
    target.style.paddingTop = 0;
    target.style.paddingBottom = 0;
    target.style.marginTop = 0;
    target.style.marginBottom = 0;
    void target.offsetHeight;
    target.style.boxSizing = 'border-box';
    target.style.transitionProperty = 'height, margin, padding';
    target.style.transitionDuration = `${duration}ms`;
    target.style.height = `${height}px`;
    target.style.removeProperty('padding-top');
    target.style.removeProperty('padding-bottom');
    target.style.removeProperty('margin-top');
    target.style.removeProperty('margin-bottom');

    window.setTimeout(() => {
      target.style.removeProperty('height');
      target.style.removeProperty('overflow');
      target.style.removeProperty('transition-duration');
      target.style.removeProperty('transition-property');
    }, duration);
  };

  Drupal.behaviors.epaAlerts = {
    attach(context, settings) {
      const alerts = once('loadEpaAlerts', 'body', context);
      alerts.forEach(alert => {
        const alertContext = drupalSettings.epaAlerts.context;
        const viewInfo = {
          view_name: `${alertContext}_alerts`,
          view_display_id: 'default',
          view_dom_id: `js-view-dom-id-${alertContext}_alerts_default`,
        };
        const ajaxSettings = {
          submit: viewInfo,
          url: '/views/ajax',
        };
        const getAlerts = Drupal.ajax(ajaxSettings);

        getAlerts.commands.insert = function (ajax, response, status) {
          if (
            response.selector ===
            `.js-view-dom-id-js-view-dom-id-${alertContext}_alerts_default`
          ) {
            const parser = new DOMParser();
            const responseHTMLNew = parser.parseFromString(
              response.data,
              'text/html'
            );
            const noResultsNew = responseHTMLNew.querySelector('.view__empty');

            if (noResultsNew === null && response.data !== '') {
              const jsDomAlert = document.querySelector(
                `.js-view-dom-id-epa-alerts--${alertContext}`,
                context
              );
              jsDomAlert.style.display = 'none';
              jsDomAlert.innerHTML = response.data;

              if (jsDomAlert.nodeType === Node.ELEMENT_NODE) {
                Drupal.attachBehaviors(jsDomAlert);
              }

              slideDown(jsDomAlert);
            }
          }
        };

        getAlerts.commands.destroyObject = function (ajax, response, status) {
          Drupal.ajax.instances[this.instanceIndex] = null;
        };

        getAlerts.execute().fail(() => {
          console.error('Failed to load alerts.');
        });
      });
    },
  };
})(Drupal);
