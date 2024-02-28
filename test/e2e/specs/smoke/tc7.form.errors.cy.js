import {
  addPageSection,
  findStringInArray,
  getErrorMessages, loginToDrupal, removePageSection,
} from '../../support/functions';

const formatFile = require('../../fixtures/formsNew.json');

describe('TC6 - Events - errors', () => {
  Cypress.on('uncaught:exception', (_err, _runnable) => false);
  before(() => {
    cy.get('body').then(() => {
      loginToDrupal('first');
    }).then(() => {
      cy.get('.block:contains("My Web Areas")').find('a:contains("Web Guide"):visible').first().click();
    }).then(() => {
      cy.contains('a', 'Add new content').click();
    })
      .then(() => {
        cy.get('.admin-list').contains('a', 'Event').click();
      })
      .then(() => {
        cy.get('.page-title').should('contain.text', 'Add Web Area: Group node (Event)');
      });
  });

  beforeEach(() => {
    cy.preserveAllCookiesOnce();
  });

  formatFile.formatItems.forEach((currentItem) => {
    it(`${currentItem.section} - ${currentItem.itemName} - Saving without required information shows errors`, () => {
      cy.get('body').then(() => {
        addPageSection(currentItem);
      }).then(() => {
        cy.get('[data-drupal-selector="edit-actions"]').first().find('input').first()
          .click();
      })
        .then(() => {
          const errors = [];
          cy.task('log', `expected: ${JSON.stringify(currentItem.errors)} - actual: ${JSON.stringify(getErrorMessages())}`);

          cy.wrap(currentItem.errors).each((currentError) => {
            if (!findStringInArray(getErrorMessages(), currentError)) {
              errors.push(currentError);
            }
          });
          cy.task('log', `errors: ${JSON.stringify(errors)}`);
          cy.wrap(errors).should('have.length', 0);
          cy.wrap(getErrorMessages()).should('have.length', currentItem.errors.length);
        });
    });

    it(`${currentItem.section} - ${currentItem.itemName} - Remove the section`, () => {
      removePageSection(currentItem);
    });
  });

  it('Description only allows 256 characters', () => {
    cy.get('body').then(() => {
      cy.get('.field--name-field-description').find('textarea').type('Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Purus gravida quis blandit turpis cursus in. {enter} Placerat in egestas erat imperdiet. Nisl purus in mollis nunc sed id. Odio aenean sed adipiscing diam donec adipiscing tristique risus nec.');
    }).then(() => {
      cy.get('[id=edit-field-description-0-value]').should('have.value', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Purus gravida quis blandit turpis cursus in. \n Placerat in egestas erat imperdiet. Nisl purus in mollis nunc sed id. Odio aenean sed');
    });
  });
});
