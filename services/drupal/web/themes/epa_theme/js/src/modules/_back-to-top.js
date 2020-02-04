export default function(threshold = 200) {
  const backToTop = document.querySelector('.back-to-top');
  if (backToTop) {
    if (!isNaN(threshold)) {
      backToTop.setAttribute('aria-hidden', 'true');
      const scrollHandler = () => {
        if (
          window.scrollY >= threshold &&
          backToTop.getAttribute('aria-hidden') === 'true'
        ) {
          backToTop.setAttribute('aria-hidden', 'false');
        } else if (
          window.scrollY < threshold &&
          backToTop.getAttribute('aria-hidden', 'false')
        ) {
          backToTop.setAttribute('aria-hidden', 'true');
        }
      };
      let stillScrolling = false;
      window.addEventListener('scroll', () => {
        if (stillScrolling !== false) {
          clearTimeout(stillScrolling);
        }
        stillScrolling = setTimeout(scrollHandler, 60);
      });
    }
  }
}
