// Eternal Links script
import Drupal from 'drupal';
import twoUp from 'two-up-element';

(function(Drupal) {
  Drupal.behaviors.beforeAfterSwipe = {
    attach() {
      twoUp();
    },
  };
})(Drupal);
