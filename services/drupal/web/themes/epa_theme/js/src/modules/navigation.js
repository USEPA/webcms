import navigation from 'uswds/src/js/components/navigation.js';

export default function() {
  const subnav = document.querySelectorAll('.menu__subnav');
  subnav.forEach((menu, index) => {
    const button = menu.previousElementSibling;
    if (button.classList.contains('usa-nav__link')) {
      const id = `extended-nav-section-${index}`;
      menu.id = id;
      button.setAttribute('aria-controls', id);
    }
  });
  navigation.on(document.body);
}
