/* eslint-disable no-inline-comments */
/* eslint-disable no-console */
/* eslint-disable prefer-object-spread */
// https://docs.cypress.io/guides/guides/plugins-guide.html

// if you need a custom webpack configuration you can uncomment the following import
// and then use the `file:preprocessor` event
// as explained in the cypress docs
// https://docs.cypress.io/api/plugins/preprocessors-api.html#Examples

// /* eslint-disable import/no-extraneous-dependencies, global-require */
// const webpack = require('@cypress/webpack-preprocessor')
const clipboardy = require('clipboardy');
const xlsx = require('node-xlsx').default;
const fs = require('fs');
const path = require('path');
const reader = require('xlsx');
const dotenvPlugin = require('cypress-dotenv');
const {downloadFile} = require('cypress-downloadfile/lib/addPlugin');
const {
  lighthouse, pa11y, prepareAudit,
} = require('cypress-audit');
const {isFileExist} = require('cy-verify-downloads');
const {rmdir} = require('fs');
const pdf = require('pdf-parse');
const readXlsx = require('./read-xlsx');

const repoRoot = path.join(__dirname, '..', '..');

const parsePdf = async(pdfName) => {
  const pdfPathname = path.join(repoRoot, pdfName);
  let dataBuffer = fs.readFileSync(pdfPathname);
  return await pdf(dataBuffer); // use async/await since pdf returns a promise
};

module.exports = (on, config) => {
  // config = dotenvPlugin(config, {}, true)

  on('before:browser:launch', (browser = {}, launchOptions) => {
    // launchOptions.push('args')
    // launchOptions.args.push('--use-file-for-fake-video-capture=c:\\my-video.y4m')
    // console.log("launch: "+launchOptions)
    // prepareAudit(launchOptions);
    if (browser.name === 'chrome' && browser.isHeadless) {
      // force screen to behave like retina
      // when running Chrome headless browsers
      // (2560x1440 in size by default)
      launchOptions.args.push('--force-device-scale-factor=2');
      launchOptions.args.push('--user-data-dir=/home/user/.config/google-chrome/Default');
      launchOptions.preferences.default['download.prompt_for_download'] = false;
    }

    return launchOptions;
  });

  on('task',
    {
      testTimings(attributes) {
        console.log('Test "%s" has finished in %dms',
          attributes.title, attributes.duration);
        console.table(attributes.commands);

        return null;
      },

      lighthouse: lighthouse((lighthouseReport) => {
        console.log(lighthouseReport); // raw lighthouse reports
      }),
      pa11y: pa11y((pa11yReport) => {
        console.log(pa11yReport); // raw pa11y reports
      }),

      log: (message) => {
        console.log(message);
        return null;
      },
      getClipboard: () => clipboardy.readSync(),

      parseXlsx: (filePath) => new Promise((resolve, reject) => {
        try {
          const jsonData = xlsx.parse(fs.readFileSync(filePath));
          resolve(jsonData);
        } catch (e) {
          reject(e);
        }
      }),
      readFileMaybe(filename) {
        if (fs.existsSync(filename)) {
          return fs.readFileSync(filename, 'utf8');
        }
        return null;
      },
      writeXlsx: (filePath) => new Promise((resolve, reject) => {
        try {
          const jsonData = xlsx.parse(fs.readFileSync(filePath));

          let workBook = reader.utils.book_new();
          const workSheet = reader.utils.json_to_sheet(jsonData);
          reader.utils.book_append_sheet(workBook, workSheet, 'response');
          let exportFileName = 'response.xls';
          reader.writeFile(workBook, exportFileName);
        } catch (e) {
          reject(e);
        }
      }),
      deleteFolder(folderName) {
        console.log('deleting folder %s', folderName);

        return new Promise((resolve, reject) => {
          rmdir(folderName, {maxRetries: 10, recursive: true}, (err) => {
            if (err) {
              console.error(err);
              return reject(err);
            }
            resolve(null);
          });
        });
      },
      getPdfContent(pdfName) {
        return parsePdf(pdfName);
      },
      isFileExist,
      downloadFile,
      downloads: (downloadspath) => fs.readdirSync(downloadspath),
    },

    {downloadFile});
  return config;
};
