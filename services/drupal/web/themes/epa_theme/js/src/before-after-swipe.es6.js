// Eternal Links script
import Drupal from 'drupal';

(function(Drupal) {
  Drupal.behaviors.beforeAfterSwipe = {
    attach(context, settings) {
      const beforeAfter = context.querySelector('.before-after-swipe');
      const slider = beforeAfter.querySelector('.before-after-swipe__slider');

      function update(e) {
        const sliderPos = e.target.value;
        e.target.parentElement.style.setProperty(
          '--split-point',
          `${sliderPos}%`
        );
        context.querySelector(
          '.before-after-swipe__slider-button'
        ).style.left = `calc(${sliderPos}%`;
      }

      slider.addEventListener('input', update);
      slider.addEventListener('change', update);
    },
  };
})(Drupal);
