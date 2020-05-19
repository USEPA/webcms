// Slideshow script
// Implements Tiny Slider library
// https://github.com/ganlanyuan/tiny-slider
import Drupal from 'drupal';
import { tns } from 'tiny-slider/src/tiny-slider';

(function(Drupal) {
  Drupal.behaviors.gallery = {
    attach(context) {
      const sliders = context.querySelectorAll('.js-slideshow');
      sliders.forEach(slider =>
        tns({
          container: slider.querySelector('.js-slideshow__container'),
          controlsContainer: slider.querySelector('.js-slideshow__controls'),
          gutter: 16,
          navContainer: slider.querySelector('.js-slideshow__nav'),
          preventScrollOnTouch: 'auto',
        })
      );
    },
  };
})(Drupal);
