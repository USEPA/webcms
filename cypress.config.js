const {defineConfig} = require('cypress');

module.exports = defineConfig({
  screenshotsFolder: 'test/e2e/screenshots',
  videosFolder: 'test/e2e/videos',
  downloadsFolder: '/home/bbruce01/Downloads/cypress/',
  jsonFolder: '/home/bbruce01/Downloads/',
  trashAssetsBeforeRuns: true,
  fixturesFolder: 'test/e2e/fixtures',
  responseTimeout: 120000,
  pageLoadTimeout: 360000,
  chromeWebSecurity: false,
  reporter: 'node_modules/mochawesome/src/mochawesome.js',

  video: false,
  reporterOptions: {
    overwrite: false,
    html: false,
    json: true,
  },
  e2e: {
    // We've imported your old cypress plugins here.
    // You may want to clean this up later by importing these.
    setupNodeEvents(on, config) {
      return require('./test/e2e/plugins/index.js')(on, config);
    },
    baseUrl: 'https://stage-www.epa.gov',
    supportFile: 'test/e2e/support/index.js',
    specPattern: 'test/e2e/specs/**/*.cy.{js,jsx,ts,tsx}',
  },
});
