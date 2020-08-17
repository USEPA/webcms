// Definition script
import Drupal from 'drupal';

(function(Drupal) {
  Drupal.behaviors.definition = {
    attach(context) {
      const definitions = context.querySelectorAll('.js-definition');
      const now = Date.now();

      definitions.forEach((definition, index) => {
        const trigger = definition.querySelector('.js-definition__trigger');
        const tooltip = definition.querySelector('.js-definition__tooltip');
        const definitionId = `definition-${now}-${index}`;

        trigger.setAttribute('aria-described-by', definitionId);
        tooltip.setAttribute('id', definitionId);
        tooltip.setAttribute('aria-hidden', true);

        definition.addEventListener('mouseenter', event => {
          openTooltip(tooltip);
        });

        definition.addEventListener('mouseleave', event => {
          closeTooltip(tooltip);
        });

        definition.addEventListener('focusin', event => {
          openTooltip(tooltip);
        });

        definition.addEventListener('focusout', event => {
          closeTooltip(tooltip);
        });
      });

      function openTooltip(tooltip) {
        tooltip.setAttribute('aria-hidden', false);
      }

      function closeTooltip(tooltip) {
        tooltip.setAttribute('aria-hidden', true);
      }
    },
  };
})(Drupal);
