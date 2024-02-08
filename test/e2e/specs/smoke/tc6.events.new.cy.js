import {
  addPageSection,
  duplicatePageSection,
  getPageFields, getTodayDate, loginToDrupal, setPageFields, verifyPageElements, verifyPageSource,
} from '../../support/functions';

const formatFile = require('../../fixtures/eventsNew.json');

describe('TC6 - Events', () => {
  Cypress.on('uncaught:exception', (_err, _runnable) => false);
  before(() => {
    loginToDrupal('first');
  });

  beforeEach(() => {
    cy.preserveAllCookiesOnce();
  });

  it(`Add New ${formatFile.contentType}`, () => {
    cy.get('.block:contains("My Web Areas")').find('a:contains("Web Guide"):visible').first().click();
    cy.contains('a', 'Add new content').click();
    cy.get('.admin-list').contains('a', formatFile.contentType).click();
    cy.get('.page-title').should('contain.text', `Add Web Area: Group node (${formatFile.contentType})`);
  });

  it(`Add New ${formatFile.contentType} - defaults`, () => {
    cy.get('.paragraphs-subform').should('have.length', 1);
    cy.contains('a', 'Web Guide').should('exist');
  });

  formatFile.formatItems.forEach((currentItem) => {
    it(`Add Item - ${currentItem.section} - ${currentItem.itemName}`, () => {
      addPageSection(currentItem);
    });

    it(`Default Value - ${currentItem.section} - ${currentItem.itemName}`, () => {
      getPageFields(currentItem);
    });
  });

  formatFile.formatItems.forEach((currentItem) => {
    it(`Set Fields - ${currentItem.section} - ${currentItem.itemName}`, () => {
      setPageFields(currentItem);
    });
  });

  it('Duplicate HTML Section', () => {
    duplicatePageSection('Body', '.paragraph-type--html', 0);
  });

  it('Save after entering all required information', () => {
    cy.get('body').then(() => {
      cy.get('[data-drupal-selector="edit-actions"]').find('input').first().click({force: true});
    }).then(() => {
      cy.get('.usa-alert--success').find('.usa-alert__text').should('contain.text', `${formatFile.contentType} ${formatFile.pageTitle} has been created.`);
    });
  });

  it('Set State to Draft Approved State', () => {
    cy.get('body').then(() => {
      cy.get('[data-drupal-selector="edit-new-state"]').first().select('Draft, approved');
      cy.get('[data-drupal-selector="edit-revision-log"]').type('Approved the draft');
    }).then(() => {
      cy.get('[data-drupal-selector="edit-submit"]').click();
      cy.get('.usa-alert__text').should('contain.text', 'The moderation state has been updated.');
    });
  });

  it('View the webpage', () => {
    cy.get('body').then(() => {
      cy.get('a:contains("View"):visible').first().click();
    }).then(() => {
      // eslint-disable-next-line cypress/no-unnecessary-waiting
      cy.wait(2000);
      verifyPageElements(formatFile.expected);
    });
  });

  it('Verify Dates in Page Source', () => {
    verifyPageSource('DC.date.created', getTodayDate('yyyy-MM-dd'));
    verifyPageSource('DC.date.modified', getTodayDate('yyyy-MM-dd'));
    verifyPageSource('DC.date.reviewed', getTodayDate('yyyy-MM-dd'), (60000 * 60 * 24 * 90));
  });

  it(`Edit ${formatFile.contentType}`, () => {
    cy.get('body').then(() => {
      cy.get('a:contains("Edit"):visible').first().click();
    }).then(() => {
      cy.wrap(formatFile.editedItems).each((currentEdit) => {
        setPageFields(currentEdit);
      });
    }).then(() => {
      cy.get('[data-drupal-selector="edit-actions"]').first().find('input').first()
        .click();
      cy.get('.usa-alert__text').should('contain.text', `${formatFile.contentType} ${formatFile.pageTitle} has been updated.`);
      verifyPageElements(formatFile.expectedEdited);
    });
  });

  it('Publish the page', () => {
    cy.get('body').then(() => {
      cy.get('[data-drupal-selector="edit-new-state"]').first().select('Published');
      cy.get('[data-drupal-selector="edit-revision-log"]').type('Published the page');
      cy.get('[id=edit-workflow-508-compliant]').check({force: true});
    }).then(() => {
      cy.get('[data-drupal-selector="edit-submit"]').click();
      cy.get('.usa-alert__text').should('contain.text', 'The moderation state has been updated.');
      cy.get('.field--tight:contains("Moderation state")').should('contain.text', 'Published');
    });
  });

  it('Revisions tab shows the latest revision as published', () => {
    cy.get('body').then(() => {
      cy.get('a:contains("Revisions"):visible').first().click();
    }).then(() => {
      cy.get('.diff-revisions').find('tr:contains("Latest revision")').should('have.length', 1);
      cy.get('.diff-revisions').find('tr:contains("Currently published revision")').should('have.length', 1);
    });
  });

  it('Dashboard content tab shows the page as published', () => {
    cy.get('body').then(() => {
      cy.get('a:contains("Group Dashboard"):visible').first().click();
    }).then(() => {
      cy.get('.views-table').find(`tr:contains("${formatFile.pageTitle}")`).find('.views-field-moderation-state').should('contain.text', 'Published');
      cy.get('.views-table').find(`tr:contains("${formatFile.pageTitle}")`).find('.views-field-moderation-state-1').should('contain.text', 'Published');
    });
  });

  it('Published Content tab shows the page as published', () => {
    cy.get('body').then(() => {
      cy.get('a:contains("Published Content"):visible').first().click();
    }).then(() => {
      cy.get('.views-table').find(`tr:contains("${formatFile.pageTitle}")`).find('.views-field-moderation-state').should('contain.text', 'Published');
    });
  });

  it('Clone the page', () => {
    cy.get('body').then(() => {
      cy.get(`a:contains("${formatFile.pageTitle}"):visible`).first().click();
      cy.get('a:contains("Clone"):visible').first().click();
      cy.get('[value="Clone"]').first().click();
    }).then(() => {
      cy.get('.usa-alert__text').should('contain.text', `The entity ${formatFile.pageTitle}`);
      cy.get('.usa-alert__text').should('contain.text', 'of type node was cloned.');
      cy.get('.field--tight:contains("Moderation state")').should('contain.text', 'Draft');
      verifyPageElements(formatFile.expected);
      verifyPageElements(formatFile.expectedEdited);
    });
  });


  it('Cloned page shows on Latest Revisions tab', () => {
    cy.get('body').then(() => {
      cy.get('a:contains("Revisions"):visible').first().click();
    }).then(() => {
      cy.get('.diff-revisions').find('tr:contains("Latest revision")').should('have.length', 1);
      cy.get('.diff-revisions').find('tr:contains("Current revision")').should('have.length', 1);
    });
  });

  it('Dashboard content tab shows the cloned page as Draft', () => {
    cy.get('body').then(() => {
      cy.get('a:contains("Group Dashboard"):visible').first().click();
    }).then(() => {
      cy.get('.views-table').find(`tr:contains("Cloned: ${formatFile.pageTitle}")`).find('.views-field-moderation-state').should('contain.text', 'Draft');
      cy.get('.views-table').find(`tr:contains("Cloned: ${formatFile.pageTitle}")`).find('.views-field-moderation-state-1').should('contain.text', 'Draft');
    });
  });

  it('Published Content tab does not show the cloned page', () => {
    cy.get('body').then(() => {
      cy.get('a:contains("Published Content"):visible').first().click();
    }).then(() => {
      cy.get('.views-table').find(`tr:contains("Cloned: ${formatFile.pageTitle}")`).should('not.exist');
    });
  });

  it('Return to View Page', () => {
    cy.get('body').then(() => {
      cy.get('a:contains("Latest Revisions")').click();
    }).then(() => {
      cy.get('.views-table').find('tbody').find(`a:contains("${formatFile.pageTitle}")`).first()
        .click();
    }).then(() => {
      verifyPageElements(formatFile.expected);
      verifyPageElements(formatFile.expectedEdited);
    });
  });

  it('Delete a cloned Event', () => {
    cy.get('body').then(() => {
      cy.get('nav').find('a:contains("My Web Areas")').first().click();
      cy.get('.block:contains("My Web Areas")').find('a:contains("Web Guide"):visible').first().click();
      cy.get(`a:contains("Cloned: ${formatFile.pageTitle}"):visible`).first().click();
      cy.get('a:contains("Delete"):visible').first().click();
      cy.get('[value="Delete"]').first().click();
    }).then(() => {
      cy.get('.usa-alert__text').should('contain.text', `The Event Cloned: ${formatFile.pageTitle}`);
      cy.get('.usa-alert__text').should('contain.text', 'has been deleted.');
    });
  });

  it('Delete an Event', () => {
    cy.get('body').then(() => {
      cy.get('nav').find('a:contains("My Web Areas")').first().click();
      cy.get('.block:contains("My Web Areas")').find('a:contains("Web Guide"):visible').first().click();
      cy.get(`a:contains("${formatFile.pageTitle}"):visible`).first().click();
      cy.get('a:contains("Delete"):visible').first().click();
      cy.get('[value="Delete"]').first().click();
    }).then(() => {
      cy.get('.usa-alert__text').should('contain.text', `The Event ${formatFile.pageTitle}`);
      cy.get('.usa-alert__text').should('contain.text', 'has been deleted.');
    });
  });
});


