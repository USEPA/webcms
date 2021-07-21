/**
 * @file
 * EPA Linkit customizations.
 */

(function ($) {

  Drupal.behaviors.epaWysiwygLinkit = {
    attach: function (context) {
      $webAreaInput = $('[name="attributes[href_web_area_content]"]');
      $defaultInput = $('[name="attributes[href_default]"]');
      $hrefInput = $('[name="attributes[href]"]');

      // Populate the defaultInput with the value of hrefInput on load, if there
      // is one.
      $('[name="attributes[href]"]', context).once('epaWysiwygLinkitInitInput').each(function() {
        if ($hrefInput.val() != '') {
          $defaultInput.val($hrefInput.val());
          $hrefInput.val('');
        }
      });


      // Clear the linkit input values when the profile is changed to reset the
      // autocomplete results.
      $('[name="attributes[select-profile]"]', context).once('epaWysiwygLinkitResetInput').change(function() {
        $defaultInput.val('');
        $webAreaInput.val('');
      });
    }
  };

})(jQuery);
