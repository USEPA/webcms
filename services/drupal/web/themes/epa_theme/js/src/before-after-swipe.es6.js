// Eternal Links script
import Drupal from 'drupal';

(function(Drupal) {
  Drupal.behaviors.beforeAfterSwipe = {
    attach(context, settings) {
      const beforeAfters = context.querySelectorAll('.before-after-swipe');

      beforeAfters.forEach(beforeAfter => {
        const slider = beforeAfter.querySelector('.before-after-swipe__slider');

        function update(e) {
          const sliderPos = e.target.value;
          e.target.parentElement.style.setProperty(
            '--split-point',
            `${sliderPos}%`
          );
          e.target.parentElement.querySelector(
            '.before-after-swipe__slider-button'
          ).style.left = `calc(${sliderPos}%`;
        }

        slider.addEventListener('input', update);
        slider.addEventListener('change', update);
      });
    },
  };
})(Drupal);
