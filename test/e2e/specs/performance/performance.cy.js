// const tabName = Cypress.env('tabName');
const filepath = Cypress.env('filepath');
const inputFile = require('../../fixtures/performanceUrls full.json');
const {testPageLoad, getTodayDate} = require('../../support/functions');
const {
  writeToFile, writeToXlsx, writeToFileAppend,
} = require('../../support/fileFunctions');

const commands = [];
let testAttributes;
const targetDate = getTodayDate('MM.DD.YYYY');
const filename = `cypress/e2e/fixtures/timings_${targetDate}.txt`;

before(() => {
  writeToFile(filename, '{\n"records" : [\n');
});

const sendTestTimings = () => {
  if (!testAttributes) { return; }

  const attr = testAttributes;
  testAttributes = null;
  writeToFileAppend(filename, `{\n\"testName\":\"${attr.title}\",\n\"timings\":\n`);
  writeToFileAppend(filename, attr.commands);
  writeToFileAppend(filename, '\n},\n');
  cy.task('testTimings', attr);
};

beforeEach(sendTestTimings);

after(() => {
  cy.get('body').then(() => {
    writeToFileAppend(filename, '{"testName":"End","timings":[{"name": "end",\n"started": 0,\n"endedAt": 0,\n"elapsed": 0}\n]\n}\n\n]\n}');
  }).then(() => {
    sendTestTimings;
  }).then(() => {
    // readFile()
    // writeToXlsx(filename)
  });
});

Cypress.on('test:before:run', () => { commands.length = 0; });

Cypress.on('test:after:run', (attributes) => {
  /* eslint-disable no-console */
  console.log('Test "%s" has finished in %dms', attributes.title, attributes.duration);
  console.table(commands);
  testAttributes = {
    title: attributes.title,
    duration: attributes.duration,
    commands: Cypress._.cloneDeep(commands),
  };
});

Cypress.on('command:start', (c) => {
  commands.push({
    name: c.attributes.name,
    started: +new Date(),
  });
});

Cypress.on('command:end', (c) => {
  const lastCommand = commands[commands.length - 1];

  if (lastCommand.name !== c.attributes.name) { throw new Error('Last command is wrong'); }

  lastCommand.endedAt = +new Date();
  lastCommand.elapsed = lastCommand.endedAt - lastCommand.started;
});

describe('Performance Tests', () => {
  inputFile.urls.forEach((page) => {
    describe(`Performance Tests for ${page.name} page`, () => {
      it(`Page loads in a reasonable time frame -${page.url}`, () => {
        testPageLoad(page.url, page.expectedLoadTime, commands);
      });
    });
  });
});
