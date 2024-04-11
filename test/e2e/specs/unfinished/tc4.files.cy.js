import {
  loginToDrupal, navigateToWebArea, setFieldValue, verifyTableSearch,
} from '../../support/functions';

const formatFile = require('../../fixtures/media.json');

describe('TC4 - Files', () => {
  Cypress.on('uncaught:exception', (_err, _runnable) => false);
  before(() => {
    loginToDrupal('first');
    cy.get('nav').find('a:contains("My Web Areas")').first().click();
  });

  beforeEach(() => {
    cy.preserveAllCookiesOnce();
  });

  it('Open Media tab', () => {
    cy.get('nav:visible').find('a:contains("Media"):visible').first().click();
    cy.get('.views-table').find('tr:contains("pdf")').should('have.length.gte', 1);
  });

  formatFile.searches.forEach((currentSearch) => {
    it(`Search Media Tab - ${currentSearch.itemName}`, () => {
      cy.get(currentSearch.itemId).then((currentElement) => {
        cy.get('body').then(() => {
          cy.wrap(currentSearch.fields).each((currentField) => {
            setFieldValue(currentElement, currentField);
          });
        })
          .then(() => {
            verifyTableSearch(currentSearch.verify);
          });
      });

      // On the Media tab, search for files by each field: Name, Tags, Author, and Type.
      // All files that belong to the filtered criteria are listed.
    });
  });

  it('wait', () => {
    cy.wait(600000);
  });
/*
  it('Test', () =>
  {

Upload an image, audio file , or link to a video File is available.
Look for the file on the Media tab in the Web Area dashboard.
Super-users should also be able to access the Media tab from the Content top level menu.

  })
  it('Test', () =>
  {
    Upload an audio, document (doc, docx, pdf, rtf, ppt, or pptx) or other file
    (csv, dtd, json, kml, kmz, smi, txt, xml, xsd, accdb, dmg, exe, msi, r, rar, swf, zip)
    using the Insert Inline Media tool (the <> icon in the WYSIWYG editor) File is available.
    Look for the file on the Media tab in the Web Area dashboard.
    Super-users should also be able to access the Media tab from the Content top level menu.

  })
  it('Test', () =>
  {

Go to one of your Web areas and from the Media tab, select the Create media button, upload an image,
audio file, link to a video, document (doc, docx, pdf, rtf, ppt, or pptx) or other file (csv, dtd, json,
  kml, kmz, smi, txt, xml, xsd, accdb, dmg, exe, msi, r, rar, swf, zip). File is available.
  Look for the file on the Media tab in the Web Area dashboard. Super-users should also be able to access
  the Media tab from the Content top level menu.

  })
  it('Test', () =>
  {
    Super-users only: On the Media tab, select the Relate media button and add an existing file to the
    Web Area File is available.
    Look for the file on the Media tab in the Web Area dashboard. Super-users should also be able to access
    the Media tab from the Content top level menu.

  })
  it('Test', () =>
  {
    Delete the recently uploaded files. Can only delete files in your web areas

  })
  it('Test', () =>
  {
    Update an existing file by selecting edit media from the drop down list, then the Choose File button,
    the updated file, and Save. date in Updated column is today's date.


  })
  */
});
