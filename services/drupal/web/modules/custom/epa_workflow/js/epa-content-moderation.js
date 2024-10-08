/**
 * @file
 * EPA Content Moderation
 *
 * Add cancel button that closes dropdown.
 */

Drupal.behaviors.epaContentModeration = {
  attach(context) {
    const contentModerations = once(
      'epa-content-moderation',
      '.js-epa-content-moderation',
      context
    );

    contentModerations.forEach(contentModeration => {
      const form = contentModeration.querySelector('#content-moderation-entity-moderation-form');

      if (form) {
        const submitButton = form.querySelector('input[type="submit"]');
        const parentDropdown = submitButton.closest('.js-epa-dropdown');

        if (parentDropdown) {
          const cancelButton =  document.createElement('button');
          cancelButton.classList.add(
            'button',
            'button--secondary',
            'js-epa-dropdown-close'
          );
          cancelButton.innerHTML = Drupal.t('Cancel');
          cancelButton.addEventListener('click', e => {
            e.preventDefault();
            parentDropdown.removeAttribute('open');
          })
          submitButton.before(cancelButton);
        }
      }
    });
  },
};
