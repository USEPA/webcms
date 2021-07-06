/**
 * @file
 * EPA Linkit customizations.
 */

(function ($) {

  Drupal.behaviors.epaWysiwygLinkit = {
    attach: function (context) {
      $webAreaInput = $('[name="attributes[href_web_area_content]"]');
      $defaultInput = $('[name="attributes[href]"]');
      // Clear the linkit input values when the profile is changed to reset the
      // autocomplete results.
      $('[name="attributes[select-profile]"]', context).once('epaWysiwygLinkit').change(function() {
        $defaultInput.val('');
        $webAreaInput.val('');
      });
    }
  };

})(jQuery);
