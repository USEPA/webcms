// Custom scripts file

// Include USWDS Javascript.
require('@uswds/uswds');

import domready from 'domready';
import navigation from './modules/navigation';
import tablesort from './modules/tablesort';
import backToTop from './modules/_back-to-top.es6';
import setScrollbarProperty from './modules/scrollbar-property.es6';
import 'svgxuse';

(function () {
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
    document.documentElement.classList.remove('no-js');
    navigation();
    tablesort();
    backToTop();
    setScrollbarProperty();
  });
})();
