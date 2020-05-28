const TRANSITIONS = {
  ease: {
    'ease-in-out': 'cubic-bezier(0.4, 0, 0.2, 1)',
    'ease-out': 'cubic-bezier(0.0, 0, 0.2, 1)',
    'ease-in': 'cubic-bezier(0.4, 0, 1, 1)',
    sharp: 'cubic-bezier(0.4, 0, 0.6, 1)',
  },
  duration: {
    shortest: '150ms',
    short: '200ms',
    standard: '375ms',
    long: '400ms',
    intro: '270ms',
    outro: '195ms',
  },
};

export default TRANSITIONS;
