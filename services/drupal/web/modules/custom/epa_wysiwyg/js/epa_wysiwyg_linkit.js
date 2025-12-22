/**
 * @file
 * EPA Linkit customizations.
 */

(function (Drupal, once) {
  Drupal.behaviors.epaWysiwygLinkitProfileSwitch = {
    attach(context) {
      once('epa-linkit-profile-switch', 'form.editor-link-dialog', context)
        .forEach((form) => {

          const radios = form.querySelectorAll(
            'input[name="attributes[select_profile]"]'
          );
          const linkInput = form.querySelector(
            'input[data-drupal-selector="edit-attributes-href"]'
          );

          if (!radios.length || !linkInput) {
            return;
          }

          radios.forEach((radio) => {
            radio.addEventListener('change', () => {
              const profile = radio.value;

              // Build new autocomplete URL
              const url = Drupal.url(
                `linkit/autocomplete/${profile}`
              );

              // Remove existing autocomplete
              if (linkInput.autocomplete) {
                linkInput.autocomplete.destroy();
              }

              // Update attribute
              linkInput.setAttribute('data-autocomplete-path', url);

              // Reattach Drupal autocomplete
              Drupal.autocomplete.attach(context, linkInput);
            });
          });
        });
    }
  };
})(Drupal, once);
