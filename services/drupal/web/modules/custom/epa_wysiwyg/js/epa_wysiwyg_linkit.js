/**
 * @file
 * EPA Linkit customizations.
 */

(function (Drupal, $, once) {

  Drupal.behaviors.epaWysiwygLinkit = {
    attach: function (context) {
      const $webAreaInput = $('[name="attributes[href_web_area_content]"]');
      const $defaultInput = $('[name="attributes[href_default]"]');
      const $hrefInput = $('[name="attributes[href]"]');

      // Populate the defaultInput with the value of hrefInput on load, if there
      // is one.
      $(once('epaWysiwygLinkitInitInput', '[name="attributes[href]"]', context)).each(function() {
        if ($hrefInput.val() != '') {
          $defaultInput.val($hrefInput.val());
          $hrefInput.val('');
        }
      });

      // Clear the linkit input values when the profile is changed to reset the
      // autocomplete results.
      $(once('epaWysiwygLinkitResetInput', '[name="attributes[epa_linkit_profile]"]', context)).change(function() {
        $defaultInput.val('');
        $webAreaInput.val('');
      });
    }
  };

})(Drupal, jQuery, once);