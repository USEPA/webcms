// Before/After Swipe script
import Drupal from 'drupal';

(function (Drupal) {
  Drupal.behaviors.beforeAfterSwipe = {
    attach(context, settings) {
      const beforeAfters = once(
        'before-after-swipe',
        '.js-before-after-swipe',
        context
      );

      beforeAfters.forEach(beforeAfter => {
        const slider = beforeAfter.querySelector(
          '.js-before-after-swipe__slider'
        );

        beforeAfter.classList.add('is-enabled');

        function update(e) {
          const sliderPos = e.target.value;
          e.target.parentElement.style.setProperty(
            '--split-point',
            `${sliderPos}%`
          );
          e.target.parentElement.querySelector(
            '.js-before-after-swipe__slider-button'
          ).style.left = `calc(${sliderPos}%`;
        }

        slider.addEventListener('input', update);
        slider.addEventListener('change', update);
      });
    },
  };
})(Drupal);
