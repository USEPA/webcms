export default function() {
  const subnav = once('navigation', '.menu--accordion .menu__subnav');
  subnav.forEach((menu, index) => {
    menu.setAttribute('hidden', true);
    const button = menu.previousElementSibling;
    if (button.classList.contains('usa-nav__link')) {
      const id = `extended-nav-section-${index}`;
      menu.id = id;
      button.setAttribute('aria-controls', id);
    }
  });
  const sideNavTrigger = document.getElementById('menu--sidenav__menu-button');
  const sideNavMenu = document.querySelector('.menu--sidenav');
  const overlay = document.getElementsByClassName('usa-overlay');
  sideNavTrigger.addEventListener(
    'click',
    function() {
      sideNavMenu.classList.toggle('is-visible');
      for (const item of overlay) {
        item.classList.toggle('is-visible');
      }
    },
    false
  );
}
