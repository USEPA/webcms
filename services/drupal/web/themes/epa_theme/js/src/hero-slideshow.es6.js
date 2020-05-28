// Slideshow script
// Implements Tiny Slider library
// https://github.com/ganlanyuan/tiny-slider
import Drupal from 'drupal';
import { tns } from 'tiny-slider/src/tiny-slider';

(function(Drupal) {
  Drupal.behaviors.heroSlideshow = {
    attach(context) {
      const sliders = context.querySelectorAll('.js-hero-slideshow');
      sliders.forEach(slider =>
        tns({
          autoplay: true,
          autoplayButtonOutput: false,
          autoplayHoverPause: true,
          autoplayTimeout: 6000,
          container: slider.querySelector('.js-hero-slideshow__container'),
          controls: false,
          mode: 'gallery',
          navContainer: slider.querySelector('.js-hero-slideshow__nav'),
          preventScrollOnTouch: 'auto',
          speed: 500,
        })
      );
    },
  };
})(Drupal);
