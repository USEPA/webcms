// Custom scripts file
// Include the USWDS Accordion script.
// This makes the component available globally. If you're only using it on certain pages,
// include it on a template-specific script file instead.
// Be sure to initialize any components as well (see init() function below.)
import domready from 'domready';
import accordion from 'uswds/src/js/components/accordion.js';
import navigation from './modules/navigation';
import banner from 'uswds/src/js/components/banner.js';
import tablesort from './modules/tablesort';
import backToTop from './modules/_back-to-top.es6';
import setScrollbarProperty from './modules/scrollbar-property.es6';
import 'svgxuse';

(function() {
  'use strict';

  // Generic function that runs on window resize.
  // An empty function is allowed here because it's meant as a placeholder,
  // but you should remove this functionality if you aren't using it!
  // eslint-disable-next-line no-empty-function
  function resizeStuff() {}

  // Runs function once on window resize.
  let timeOut = false;
  window.addEventListener('resize', () => {
    if (timeOut !== false) {
      clearTimeout(timeOut);
    }

    // 200 is time in miliseconds.
    timeOut = setTimeout(resizeStuff, 200);
  });

  domready(() => {
    accordion.on(document.body);
    banner.on(document.body);
    navigation(); // If used with the USWDS accordion component, the navigation must run after it.
    tablesort();
    backToTop();
    setScrollbarProperty();
  });
})();
