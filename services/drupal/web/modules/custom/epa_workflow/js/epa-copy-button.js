/**
 * @file
 * EPA Copy Button
 *
 * Copy data to the user’s clipboard.
 */

Drupal.behaviors.epaCopyButton = {
  attach(context) {
    const windowHost = window.location.hostname;

    once('epa-copy-button', '.js-epa-copy-button', context).forEach(copyButton => {
      const copyValue = copyButton.dataset.copyValue;

      if (!copyValue) {
        return;
      }

      copyButton.addEventListener('click', event => {
        event.preventDefault();
        navigator.clipboard.writeText(copyValue);
        alert(Drupal.t(
          'Copied “@value” to your clipboard.',
          {
            '@value': copyValue
          }
        ));
      });
    });
  },
};
