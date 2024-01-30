import {
  addPageSection,
  loginToDrupal, setFormatting, setPageFields, verifyFormatting, verifyPageElements,
} from '../../support/functions';

const formatFile = require('../../fixtures/wysiwyg.json');

// TODO: Add this back to JSON file when definitions is working.  currently does not bring up data 
/*

          {
            "formatType": "Definitions",
            "optionType": "dialog",
            "startSelection": "definitions",
            "endSelection": "definitions",
            "fields":
            [
              {
                "fieldName": "Filter by Dictionary", "fieldValue": "", "fieldType": "select"
              }
            ]
          },

*/

describe('TC2 - WYSIWYG', () => {
  Cypress.on('uncaught:exception', (_err, _runnable) => false);
  before(() => {
    cy.get('body').then(() => {
      loginToDrupal('first');
    }).then(() => {
      cy.get('.block:contains("My Web Areas")').find('a:contains("Web Guide"):visible').first().click();
      cy.contains('a', 'Add new content').click();
      cy.get('.admin-list').contains('a', formatFile.contentType).click();
    })
      .then(() => {
        cy.get('[id=cke_edit-field-paragraphs-0-subform-field-body-0-value]').find('.cke_wysiwyg_frame').then((currentIframe) => {
          cy.get('body').then(() => {
            cy.wrap(formatFile.texts).each((currentText) => {
              cy.wrap(currentIframe.contents()).find('body').type(`${currentText}{enter}`, {force: true});
            });
          });
        });
      });
  });

  beforeEach(() => {
    cy.preserveAllCookiesOnce();
  });

  formatFile.formats.forEach((currentFormat) => {
    it(`${currentFormat.formatType} - Format text`, () => {
      cy.get('table:contains("Body")').parent().find('.paragraph-type-label:contains("HTML")').first()
        .parents('tr')
        .then((currentObject) => {
          setFormatting(currentObject, currentFormat);
        });
    });
  });

  it('WYSIWYG buttons are in the correct order', () => {
    const actual = [];
    cy.get('body').then(() => {
      cy.get('[id=cke_1_top]').find('a').each((currentLink) => {
        actual.push(currentLink.prop('title'));
      });
    }).then(() => {
      cy.wrap(actual).should('deep.equal', formatFile.formatOptions);
    });
  });

  it('Save after entering all required information', () => {
    cy.get('body').then(() => {
      cy.wrap(formatFile.final).each((currentItem) => {
        cy.get('body').then(() => {
          addPageSection(currentItem);
        }).then(() => {
          setPageFields(currentItem);
        });
      });
    }).then(() => {
      cy.get('[data-drupal-selector="edit-submit"]').first().click();
    }).then(() => {
      cy.get('.usa-alert').should('contain.text', 'Basic page Wysiwyg has been created.');
    });
  });

  it('View the webpage', () => {
    cy.get('body').then(() => {
      cy.get('a:contains("View"):visible').first().click();
    }).then(() => {
      verifyPageElements(formatFile.expected);
    });
  });
});
