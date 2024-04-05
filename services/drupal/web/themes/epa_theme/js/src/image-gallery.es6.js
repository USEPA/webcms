// Image Gallery script
// Implements Tiny Slider library
// https://github.com/ganlanyuan/tiny-slider
import Drupal from 'drupal';
import { tns } from 'tiny-slider/src/tiny-slider';

(function (Drupal) {
  Drupal.behaviors.imageGallery = {
    attach(context) {
      const sliders = once('image-gallery', '.js-image-gallery', context);
      sliders.forEach(slider =>
        tns({
          container: slider.querySelector('.js-image-gallery__container'),
          controlsContainer: slider.querySelector(
            '.js-image-gallery__controls'
          ),
          gutter: 16,
          navContainer: slider.querySelector('.js-image-gallery__nav'),
          preventScrollOnTouch: 'auto',
        })
      );
    },
  };
})(Drupal);
