/**
 * JavaScript for hover and click behavior on terms added via the Drupal Add Definitions module.
 * Hover over a term to show its definition and move the mouse off to hide the definition,
 * or click it to show its definition, which will remain visible until clicked again.
 */
(function ($) {
  'use strict';

  Drupal.behaviors.addDefinitions = {
    attach: function (context, settings) {
      var fadeDuration = 100;
      var maxZIndex = 100;
      var lockedClasses = 'termlookup-js-tooltip-hover termlookup-js-tooltip-locked';

      $('span.termlookup-custom', context).each(function () {
        this.className = 'termlookup-js-custom';
        this.parentNode.className = 'termlookup-js-tooltip';
      });

      function desktopInit() {
        $('a.termlookup-js-tooltip').off('.mobile').on('click.desktop', function () {
          if (this.getAttribute('data-popup-lock') !== 'true') {
            fadeInPopup(this);
          }
          console.log(this.getAttribute('data-popup-lock'));
        })
        .on('mouseleave.desktop', function () {
          if (this.getAttribute('data-popup-lock') !== 'true') {
            hidePopup(this);
          }
          console.log(this.getAttribute('data-popup-lock'))
        })
        .on('click.desktop', function () {
          clickPopup(this);
        });
      }

      function mobileInit() {
        $('a.termlookup-js-tooltip').off('.desktop').on('click.mobile', function (e) {
          e.preventDefault();
          showModal($(this));
        });

        $('body').on('click.mobile', '.definition-modal', function () {
          hideModal();
        });
      }

      // Determine if page is mobile width.
      var isMobile = function () {
        console.log($(window).width());
        return ($(window).width() < 800) ? true : false;
      };

      // Create or destroy event handlers.
      var setEventHandlers = function () {
        if (isMobile()) {
          console.log(isMobile());
          mobileInit();
        }
        else {
          console.log('james');
          desktopInit();
          hideModal();
        }
      };

      var mobileCheck = debounce(function () {
        setEventHandlers();
      }, 250);

      window.addEventListener('resize', mobileCheck);

      // Set event handlers on page load.
      setEventHandlers();

      function debounce(func, wait, immediate) {
        var timeout;
        return function () {
          var context = this;
          var args = arguments;
          var later = function () {
            timeout = null;
            if (!immediate) {
              func.apply(context, args);
            }
          };
          var callNow = immediate && !timeout;

          clearTimeout(timeout);
          timeout = setTimeout(later, wait);
          if (callNow) {
            func.apply(context, args);
          }
        };
      }

      // Show modal.
      function showModal(target) {
        // If modal element isn't there create it.
        if (!$('.definition-modal').length) {
          $('body').append($('<div class="definition-modal hidden" />'))
          .append($('<div class="modal-overlay"/>'));
        }

        var content = target.find('.termlookup-js-custom').html();
        var modal = $('.definition-modal');
        var overlay = $('.modal-overlay');

        // Update modal content.
        modal.html(content);

        // Display Modal.
        modal.addClass('is-active');
        overlay.addClass('is-active');
      }

      function hideModal() {
        var modal = $('.definition-modal');
        var overlay = $('.modal-overlay');

        modal.removeClass('is-active');
        overlay.removeClass('is-active');
      }

      function clickPopup(target) {
        if (!$(target).find('span.termlookup-js-custom', context).is(':visible')) {
          showPopup(target);
        }

        if (target.getAttribute('data-popup-lock') !== 'true') {
          closeAllPopups();
          lockPopup(target);
        }
        else {
          unlockPopup(target);
          closeAllPopups();
        }
      }

      function lockPopup(target) {
        target.className = lockedClasses;
        target.setAttribute('data-popup-lock', 'true');
        target.style.zIndex = ++maxZIndex;
      }

      // Unlock and hide immediately.
      function unlockPopup(target) {
        $(target).removeAttr('data-popup-lock');
        hidePopup(target);
      }

      // Show popup, fading in.
      function fadeInPopup(target) {
        var $dropdown = $(target).find('span.termlookup-js-custom', context);

        $dropdown.fadeOut(0, function () {
          showPopup(target);
        });
      }

      // Show popup immediately.
      function showPopup(target) {
        target.className = 'termlookup-js-tooltip-hover';
        target.style.zIndex = ++maxZIndex;
        $(target).find('span.termlookup-js-custom').fadeIn(fadeDuration);
      }

      // Hide by fading out.
      function hidePopup(target) {
        var $dropdown = $(target).find('span.termlookup-js-custom', context);

        $dropdown.fadeOut(fadeDuration, function () {
          target.className = 'termlookup-js-tooltip';
        });
      }

      // Close all visibible popups, to be used when locking a popup.
      function closeAllPopups() {
        unlockPopup($('a.termlookup-js-tooltip-locked', context));
      }
    }
  };
})(jQuery);
