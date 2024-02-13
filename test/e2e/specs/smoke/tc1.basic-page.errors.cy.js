import {
  addPageSection,
  getErrorMessages, loginToDrupal, removePageSection, setPageFields,
} from '../../support/functions';

const formatFile = require('../../fixtures/basicPageErrors.json');

describe('TC1 - Basic Page: SuperUser', () => {
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
        cy.get('.admin-list').contains('a', 'Basic page').click();
      })
      .then(() => {
        cy.get('.page-title').should('contain.text', 'Add Web Area: Group node (Basic page)');
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
        setPageFields(currentItem);
      }).then(() => {
        cy.get('[data-drupal-selector="edit-actions"]').first().find('input').first()
          .click();
      })
        .then(() => {
          cy.task('log', `expected: ${JSON.stringify(currentItem.errors.sort())} - actual: ${JSON.stringify(getErrorMessages().sort())}`);
          cy.wrap(getErrorMessages().sort()).should('deep.equal', currentItem.errors.sort());
        });
    });

    it(`${currentItem.section} - ${currentItem.itemName} - Remove the section`, () => {
      removePageSection(currentItem, 0);
    });
  });

  it('Description only allows 256 characters', () => {
    cy.get('[id=edit-field-description-0-value]').should('have.value', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Purus gravida quis blandit turpis cursus in. \n Placerat in egestas erat imperdiet. Nisl purus in mollis nunc sed id. Odio aenean sed');
  });

  it('Save after entering all required information', () => {
    const final =
    [
      {
        section: 'Body',
        itemId: '.paragraph-type--html',
        itemName: 'HTML',
        itemIndex: 0,
        fields: [
          {
            fieldName: 'iframe', fieldType: 'iframe', fieldValue: 'first html in body section', fieldDefault: '',
          },
        ],
      },
      {
        section: '',
        itemId: '[id=edit-field-channel-wrapper]',
        itemName: 'Channel',
        fields: [
          {
            fieldName: '.form-type-checkbox:contains("About EPA")', fieldValue: true, fieldType: 'checkbox', fieldDefault: false,
          },
        ],
      },
    ];

    cy.get('body').then(() => {
      cy.wrap(final).each((currentItem) => {
        cy.get('body').then(() => {
          addPageSection(currentItem);
        }).then(() => {
          setPageFields(currentItem);
        });
      });
    }).then(() => {
      cy.get('[data-drupal-selector="edit-actions"]').first().find('input').first()
        .click();
    }).then(() => {
      cy.get('.usa-alert--success').find('.usa-alert__text').should('contain.text', 'Basic page New Basic Page Test has been created.');
    });
  });
});
