// Media embed with link
import Drupal from 'drupal';

(function(Drupal) {
  Drupal.behaviors.mediaLink = {
    attach(context) {
      const mediaImages = context.querySelectorAll(
        'a > .figure > .figure__media > img'
      );

      // Move anchors that wrap the figure to wrap the img instead.
      mediaImages.forEach(mediaImage => {
        const mediaObject = mediaImage.parentNode.parentNode.parentNode;
        const mediaFigure = mediaObject.firstElementChild.cloneNode(true);
        const mediaLink = mediaObject.cloneNode();

        mediaLink.appendChild(mediaImage);
        mediaFigure.firstElementChild.firstElementChild.replaceWith(mediaLink);
        mediaObject.replaceWith(mediaFigure);
      });
    },
  };
})(Drupal);
