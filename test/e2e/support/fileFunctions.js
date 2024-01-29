/* eslint-disable consistent-return */
const downloadsFolder = Cypress.config('downloadsFolder');
const tableInfo = require('../../../cypress/fixtures/tableInfo.json');
const reportSections = require('../fixtures/report.sections.json');
//* ****************************************************File Methods*************************** */
export function writeToFile(filename, text) {
  cy.writeFile(filename, text);
}

export function writeToFileAppend(filename, text) {
  cy.writeFile(filename, text, {flag: 'a+'});
}

export function zipExtract(filePath) {
  cy.task('extractZipFile', filePath);
}

export function readValueFromJsonFile(value) {
  cy.task('log', tableInfo[value]);
  return tableInfo[value];
}

export function readFile(filename) {
  cy.readFile(filename).then((currentFileData) => {
    const recJson = JSON.parse(currentFileData);
    const recJsonArray = recJson.records;
    cy.task('log', `file: ${currentFileData}`);
    cy.task('log', `file: ${JSON.stringify(recJsonArray)}`);
    cy.wrap(recJsonArray).each((recordNode) => {
      const testname = recordNode.testName;
      const {timings} = recordNode;

      if (typeof testname !== 'undefined') {
        cy.wrap(timings).each(($timing) => {
          const timingName = $timing.name;
        });
      }
    });
  });
}

export function getLandscapeURLFromFile(filename) {
  cy.task('downloads', downloadsFolder).each((currentFile) => {
    cy.task('log', `current file: ${currentFile}`);
    if ((!currentFile.includes('.nfs')) && (currentFile.includes(filename))) {
      cy.readFile(`${downloadsFolder}/${currentFile}`).then((currentFileData) => {
        const clowderStart = currentFileData.indexOf('https://clowder.edap-cluster.com');
        const clowderEnd = currentFileData.indexOf(')', clowderStart);
        const clowderUrl = currentFileData.substring(clowderStart, clowderEnd);
        cy.task('log', `${clowderStart} - ${clowderEnd}`);
        // eslint-disable-next-line no-return-assign
        cy.window().then(win => win.location.href = clowderUrl);
        // cy.visit(clowderUrl)
      });
    }
  });
}

export function createDLFile() {
  const dataLandscapes = [];
  let fileJson = {};
  cy.get('body').then(() => {
    cy.task('downloads', downloadsFolder).each((currentFile) => {
      if ((!currentFile.includes('.nfs')) && (!currentFile.includes('.zip'))) {
        cy.get('body').then(() => {
          cy.readFile(`${downloadsFolder}/${currentFile}`).then((currentFileData) => {
            const clowderStart = currentFileData.indexOf('https://clowder.edap-cluster.com/api/files/');
            const clowderEnd = currentFileData.indexOf(')', clowderStart);
            const clowderUrl = currentFileData.substring(clowderStart, clowderEnd);
            cy.task('log', `${clowderStart} - ${clowderEnd} - clowder url: ${currentFileData.substring(clowderStart, clowderEnd)}`);
            const dtxsid = currentFile.substring(0, currentFile.indexOf('_'));
            fileJson = {};
            fileJson.dtxsid = dtxsid;
            fileJson.filename = currentFile;
            fileJson.clowderUrl = clowderUrl;

            const fileinfo = [];
            fileinfo.push(currentFile);
            fileinfo.push(clowderUrl);
            dataLandscapes.push(fileJson);
          });
        }).then(() => {
          cy.request(fileJson.clowderUrl).then(() => {
          });
        });
      }
    });
  }).then(() => {
    writeToFile('cypress/fixtures/dataLandscapeReports.json', dataLandscapes);
  });
}


export function getPositions(orig, splitValues) {
  let positions = [];
  for (let x = 0; x < splitValues.length; x += 1) {
    const foundIndex = orig.indexOf(splitValues[x]);
    if (foundIndex > 0) {
      let positionJson = {};
      positionJson.section = splitValues[x];
      positionJson.index = foundIndex;
      positions.push(positionJson);
    }
  }
  positions.sort((a, b) => a.index - b.index);

  return positions;
}


export function verifyPdfPage(sectionName, chemical, expectedText) {
  cy.task('log', `Verifying PDF: ${sectionName} - ${chemical}`);
  let pagenum = -1;
  cy.task('downloads', downloadsFolder).each((currentFileData) => {
    if (!currentFileData.includes('.nfs')) {
      cy.task('getPdfContent', currentFileData).then((content) => {
        const pdfContent = JSON.stringify(content).replace('content: ', '');
        const pdfText = JSON.stringify(JSON.parse(pdfContent).text).split('\\n\\n\\n');
        cy.wrap(pdfText).each((page, pindex2) => {
          if (page.includes(chemical) && pindex2 >= pagenum) {
            pagenum = pindex2;
            expect(page).to.contain(expectedText);
            return false;
          }
        });
      });
    }
  });
}

export function verifyPdfPageRows(sectionName, chemical, expected) {
  let pagenum = -1;
  cy.task('downloads', downloadsFolder).each((currentFileData) => {
    if (!currentFileData.includes('.nfs')) {
      cy.task('getPdfContent', currentFileData).then((content) => {
        const pdfContent = JSON.stringify(content).replace('content: ', '');
        const pdfText = JSON.stringify(JSON.parse(pdfContent).text).split('\\n\\n\\n');
        cy.get('body').then(() => {
          cy.wrap(pdfText).each((page, pindex) => {
            if (page.includes(sectionName)) {
              pagenum = pindex;
              return false;
            }
          });
        })
          .then(() => {
            cy.wrap(pdfText).each((page, pindex2) => {
              if (page.includes(chemical) && pindex2 >= pagenum) {
                pagenum = pindex2;
                cy.task('log', `page2: ${pdfText[pagenum]}`);

                const pageLines = page.split('\\n');
                cy.task('log', pageLines);
                cy.wrap(pageLines).should('have.length', expected);
                return false;
              }
            });
          });
      });
    }
  });
}

export function verifyPdfByFileName(sectionName, fileName, expectedText) {
  cy.task('log', `Verifying PDF: ${sectionName} - ${fileName}`);
  cy.task('downloads', downloadsFolder).each((currentFileData) => {
    if ((!currentFileData.includes('.nfs')) && (currentFileData.includes(fileName))) {
      cy.task('getPdfContent', currentFileData).then((content) => {
        const pdfContent = JSON.stringify(content.text);
        const pdfText = parseStringIntoPages(pdfContent, reportSections.sections);

        cy.task('log', `\nresults: ${JSON.stringify(pdfText)} \nexpected: ${expectedText}`);
        cy.wrap(pdfText[sectionName].split('\\n').join(' ')).should('contain', expectedText);
      });
    }
  });
}

export function verifyOccurencesInPDFSection(sectionName, fileName, expectedText, expectedCount) {
  cy.task('log', `Verifying PDF Occurences: ${sectionName} - ${fileName}`);
  cy.task('downloads', downloadsFolder).each((currentFile) => {
    if ((!currentFile.includes('.nfs')) && (currentFile.includes(fileName))) {
      cy.task('getPdfContent', currentFile).then((content) => {
        const pdfContent = JSON.stringify(content.text);
        const pdfText = parseStringIntoPages(pdfContent, reportSections.sections);
        const currentSection = pdfText[sectionName];
        const actualCount = (currentSection.split(expectedText).length - 1);
        cy.wrap(actualCount).should('eq', expectedCount);
      });
    }
  });
}


export function parseStringIntoPages(orig, splitValues) {
  const positionIndexes = getPositions(orig, splitValues);
  cy.task('log', `\npositions: ${JSON.stringify(positionIndexes)}`);
  let positions = {};
  for (let x = 0; x < positionIndexes.length; x += 1) {
    const currentSection = positionIndexes[x].section;
    const currentPosition = positionIndexes[x].index;
    let nextPosition = orig.length;
    if (typeof positionIndexes[(x + 1)] !== 'undefined') { nextPosition = positionIndexes[(x + 1)].index; }


    positions[currentSection] = orig.substring(currentPosition, nextPosition);
  }
  return positions;
}

export function verifyPdfRowsByFileName(sectionName, fileName, expected) {
  let pagenum = -1;
  cy.task('downloads', downloadsFolder).each((currentFileData) => {
    if (!currentFileData.includes('.nfs') && currentFileData.includes(fileName)) {
      cy.task('getPdfContent', currentFileData).then((content) => {
        const pdfContent = JSON.stringify(content).replace('content: ', '');
        const pdfText = JSON.stringify(JSON.parse(pdfContent).text).split('\\n\\n\\n');
        cy.get('body').then(() => {
          cy.wrap(pdfText).each((page, pindex) => {
            if (page.includes(sectionName)) {
              pagenum = pindex;
              return false;
            }
          });
        })

          .then(() => {
            cy.wrap(pdfText).each((page, pindex2) => {
              if (pindex2 >= pagenum) {
                pagenum = pindex2;
                cy.task('log', `page2: ${pdfText[pagenum]}`);

                const pageLines = page.split('\\n');
                cy.wrap(pageLines).should('have.length', expected);
                return false;
              }
            });
          });
      });
    }
  });
}

export function verifyOccurenceCountInPdfByFileName(sectionName, fileName, expectedText, expectedCount) {
  cy.task('log', `Verifying PDF: ${sectionName} - ${fileName} - expected Text: ${expectedText} - expectedCount: ${expectedCount}`);
  const pagenum = [];
  let found = false;
  cy.task('downloads', downloadsFolder).each((currentFileData) => {
    if ((!currentFileData.includes('.nfs')) && (currentFileData.includes(fileName))) {
      found = true;
      cy.task('getPdfContent', currentFileData).then((content) => {
        const pdfContent = JSON.stringify(content).replace('content: ', '');
        const pdfText = JSON.stringify(JSON.parse(pdfContent).text).split('\\n\\n\\n');
        cy.wrap('body').then(() => {
          cy.wrap(pdfText).each((page, pindex) => {
            if (page.includes(sectionName)) { pagenum.push(pindex); }
          });
        })

          .then(() => {
            let actualCount = 0;
            cy.get('body').then(() => {
              cy.wrap(pagenum).each((currentPageNum) => {
                cy.wrap(pdfText).then((currentPage) => {
                  actualCount += (currentPage[currentPageNum].split(expectedText).length - 1);
                });
              });
            }).then(() => {
              expect(actualCount, `expectedText:${expectedText}`).to.equal(expectedCount);
            });
          });
      });
    }
  });
}
