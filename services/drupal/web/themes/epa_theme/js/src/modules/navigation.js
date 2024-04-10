export default function () {
  const subnav = once('navigation', '.menu--main .menu__subnav');
  subnav.forEach((menu, index) => {
    menu.setAttribute('hidden', true);
    const button = menu.previousElementSibling;
    if (button.classList.contains('usa-nav__link')) {
      const id = `extended-nav-section-${index}`;
      menu.id = id;
      button.setAttribute('aria-controls', id);
    }
  });

  const mobileMQ = window.matchMedia('(max-width: 54.99em)');

  if (mobileMQ.matches) {
    const mobileMenuButton = document.querySelector('.l-header__menu-button');
    const mobileMenuNav = document.querySelector('.usa-nav--epa');
    let focusableMM;
    let numberFocusElementsMM;
    let firstFocusableElementMM;
    let lastFocusableElementMM;
    let priorLastElementMM;

    mobileMenuButton.addEventListener('click', function() {
      if (!focusableMM) {
        focusableMM = Array.from(
          mobileMenuNav.querySelectorAll(
            'button, [href], input, select, textarea'
          )
        ).filter(item => item.tabIndex !== -1 && item.hidden !== true);
        numberFocusElementsMM = focusableMM.length;
        firstFocusableElementMM = focusableMM[0];
        lastFocusableElementMM = focusableMM[numberFocusElementsMM - 1];

        const lastFocusParentMM = lastFocusableElementMM.closest('ul');

        if (getComputedStyle(lastFocusParentMM).display === 'none') {
          priorLastElementMM = lastFocusableElementMM;
          lastFocusableElementMM = lastFocusParentMM.previousElementSibling;
        }
      }

      if (lastFocusableElementMM) {
        lastFocusableElementMM.addEventListener('click', function() {
          const swapMM = lastFocusableElementMM;
          lastFocusableElementMM = priorLastElementMM;
          priorLastElementMM = swapMM;
        });
      }
    });

    mobileMenuNav.addEventListener('keydown', event => {
      if (event.key === 'Tab') {
        if (
          event.shiftKey &&
          document.activeElement === firstFocusableElementMM
        ) {
          event.preventDefault();
          lastFocusableElementMM.focus();
        } else if (
          document.activeElement === lastFocusableElementMM &&
          !event.shiftKey
        ) {
          event.preventDefault();
          firstFocusableElementMM.focus();
        }
      }
    });
  }
}
