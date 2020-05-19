import STATE_CLASSES from '../constants/_STATE_CLASSES.es6';
import { TRANSITIONS } from '../constants/_GESSO.es6';
import { debounce } from 'throttle-debounce';

class Slider {
  constructor(slides, pager) {
    this.slides = slides;
    this.pager = pager;
    this.pagerLinks = pager.querySelectorAll('.tabs__link');
    this._setUpLink = this._setUpLink.bind(this);
  }

  init() {
    this.pagerLinks.forEach(this._setUpLink);
    this._calculateHeight();
    window.addEventListener(
      'resize',
      debounce(60, false, this._calculateHeight.bind(this))
    );
    if (document.readyState === 'complete') {
      this._calculateHeight();
    } else {
      window.addEventListener('load', this._calculateHeight.bind(this));
    }
  }

  _calculateHeight() {
    const maxHeight = Array.prototype.slice
      .call(this.slides.children)
      .reduce(
        (previousValue, currentItem) =>
          currentItem.offsetHeight > previousValue
            ? currentItem.offsetHeight
            : previousValue,
        0
      );
    this.slides.style.height = `${maxHeight}px`;
  }

  _setUpLink(link) {
    const targetId = link.getAttribute('href');
    const target = this.slides.querySelector(targetId);
    if (target) {
      link.addEventListener('click', event => {
        event.preventDefault();
        this.changeSlide(link, target);
      });
    }
  }

  changeSlide(newLink, newSlide) {
    const activeLink = this.pager.querySelector(`a.${STATE_CLASSES.active}`);
    if (activeLink && activeLink !== newLink) {
      this._hideSlide(
        activeLink,
        this.slides.querySelector(`.${STATE_CLASSES.active}`)
      );
    }
    this._revealSlide(newLink, newSlide);
  }

  _hideSlide(link, slide) {
    link.classList.remove(STATE_CLASSES.active);
    slide.classList.add('is-transitioning');
    slide.classList.remove(STATE_CLASSES.active);
    setTimeout(() => {
      slide.classList.remove('is-transitioning');
    }, parseInt(TRANSITIONS.duration.standard));
  }

  _revealSlide(link, slide) {
    link.classList.add(STATE_CLASSES.active);
    slide.classList.add(STATE_CLASSES.active);
  }
}

export default Slider;
