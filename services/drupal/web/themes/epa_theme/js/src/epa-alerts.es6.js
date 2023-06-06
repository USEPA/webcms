/* eslint-disable */
import Drupal from 'drupal';

(function(Drupal) {
  let slideDown = (target, duration = 500) => {
    target.style.removeProperty('display');
    let display = window.getComputedStyle(target).display;
    let height = target.offsetHeight;

    if (display === 'none') {
      display = 'block';
    }

    target.style.display = display;
    target.style.overflow = 'hidden';
    target.style.height = 0;
    target.style.paddingTop = 0;
    target.style.paddingBottom = 0;
    target.style.marginTop = 0;
    target.style.marginBottom = 0;
    target.offsetHeight;
    target.style.boxSizing = 'border-box';
    target.style.transitionProperty = "height, margin, padding";
    target.style.transitionDuration = duration + 'ms';
    target.style.height = height + 'px';
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
  }

  Drupal.behaviors.epaAlerts = {
    attach(context, settings) {
      const alerts = once('loadEpaAlerts', 'body', context);
      alerts.forEach(alert => {
        var alertContext = drupalSettings.epaAlerts.context;

        var viewInfo = {
          view_name: alertContext + '_alerts',
          view_display_id: 'default',
          view_dom_id: 'js-view-dom-id-' + alertContext + '_alerts_default',
        };

        var ajaxSettings = {
          submit: viewInfo,
          url: '/views/ajax'
        };

        var getAlerts = Drupal.ajax(ajaxSettings);

        getAlerts.commands.insert = function(ajax, response, status) {
          if (response.selector == '.js-view-dom-id-js-view-dom-id-' + alertContext + '_alerts_default') {
            const parser = new DOMParser();
            let responseHTMLNew = parser.parseFromString(response.data, 'text/html');
            let noResultsNew = responseHTMLNew.querySelector('.view__empty');

            if (noResultsNew == null) {
              let jsDomAlert = document.querySelector('.js-view-dom-id-epa-alerts--' + alertContext, context);
              jsDomAlert.style.display = 'none';
              jsDomAlert.innerHTML = response.data;
              slideDown(jsDomAlert);

              if (jsDomAlert.nodeType === Node.ELEMENT_NODE) {
                Drupal.attachBehaviors(jsDomAlert);
              }
            }
          }
        };

        getAlerts.commands.destroyObject = function(ajax, response, status) {
          Drupal.ajax.instances[this.instanceIndex] = null;
        };

        getAlerts.execute();
      });
    },
  };
})(Drupal);
