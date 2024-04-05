// Slideshow script
// Implements Tiny Slider library
// https://github.com/ganlanyuan/tiny-slider
import Drupal from 'drupal';
import { tns } from 'tiny-slider/src/tiny-slider';

(function (Drupal) {
  Drupal.behaviors.heroSlideshow = {
    attach(context) {
      const sliders = once('hero-slideshow', '.js-hero-slideshow', context);
      sliders.forEach(slider => {
        const sliderObject = tns({
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
        });

        // Stop autoplay after it has looped once through all slides.
        sliderObject.events.on('transitionEnd', function () {
          const sliderInfo = sliderObject.getInfo();
          if (sliderInfo.displayIndex === 1) {
            sliderInfo.container.dataset.sliderNoAutoplay = true;
            sliderObject.pause();
          }
        });

        // Pause autoplay when focus moves into slider.
        slider.addEventListener('focusin', function () {
          sliderObject.pause();
        });

        // Restart autoplay when moving focus out of slider if still within
        // first loop.
        slider.addEventListener('focusout', function () {
          const sliderInfo = sliderObject.getInfo();
          if (!sliderInfo.container.dataset.sliderNoAutoplay) {
            sliderObject.play();
          }
        });
      });
    },
  };
})(Drupal);
