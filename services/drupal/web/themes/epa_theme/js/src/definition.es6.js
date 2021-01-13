// Definition script
import Drupal from 'drupal';

(function(Drupal) {
  Drupal.behaviors.definition = {
    attach(context) {
      const definitions = context.querySelectorAll('.js-definition');
      const now = Date.now();

      definitions.forEach((definition, index) => {
        const parent = definition.parentNode;
        const trigger = definition.querySelector('.js-definition__trigger');
        const tooltip = definition.querySelector('.js-definition__tooltip');
        const definitionId = `definition-${now}-${index}`;

        trigger.setAttribute('aria-described-by', definitionId);
        tooltip.setAttribute('id', definitionId);
        tooltip.setAttribute('aria-hidden', true);

        trigger.addEventListener('click', event => {
          if (tooltip.getAttribute('aria-hidden')) {
            trigger.focus();
          }
        });

        definition.addEventListener('focusin', event => {
          openTooltip(tooltip, parent);
        });

        definition.addEventListener('focusout', event => {
          closeTooltip(tooltip, parent);
        });
      });

      function openTooltip(tooltip, parent) {
        tooltip.setAttribute('aria-hidden', false);
        parent.style.position = 'relative';
      }

      function closeTooltip(tooltip, parent) {
        tooltip.setAttribute('aria-hidden', true);
        parent.style.position = null;
      }
    },
  };
})(Drupal);
