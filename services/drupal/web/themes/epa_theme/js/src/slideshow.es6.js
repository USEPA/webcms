// Custom scripts file
// Include the USWDS Accordion script.
// This makes the component available globally. If you're only using it on certain pages,
// include it on a template-specific script file instead.
// Be sure to initialize any components as well (see init() function below.)
import Drupal from 'drupal';
import { tns } from 'tiny-slider/src/tiny-slider';

(function(Drupal) {
  Drupal.behaviors.gallery = {
    attach(context) {
      const sliders = context.querySelectorAll('.js-slideshow');
      sliders.forEach(slider =>
        tns({
          arrowKeys: true,
          container: slider.querySelector('.js-slideshow__container'),
          controlsContainer: slider.querySelector('.js-slideshow__controls'),
          nav: false,
        })
      );
    },
  };
})(Drupal);
