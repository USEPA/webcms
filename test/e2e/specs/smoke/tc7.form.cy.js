import {
  addContent,
  addPageSection,
  duplicatePageSection,
  editWebformElements,
  getPageFields, getTodayDate, loginToDrupal, navigateToWebArea, setPageFields, setWebformFields,
  verifyPageElements, verifyPageSource,
} from '../../support/functions';

const formatFile = require('../../fixtures/formsNew.json');

describe(formatFile.testCaseName, () => {
  Cypress.on('uncaught:exception', (_err, _runnable) => false);
  before(() => {
    loginToDrupal('first');
  });

  beforeEach(() => {
    cy.preserveAllCookiesOnce();
  });

  it(`Add New ${formatFile.contentType}`, () => {
    navigateToWebArea('Web Guide');
    addContent(formatFile.contentType);
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
    duplicatePageSection('Body', '.paragraph-type--html');
  });

  it('Save after entering all required information', () => {
    cy.get('[data-drupal-selector="edit-actions"]').find('input').first().click();
    cy.get('.usa-alert').should('contain.text', `${formatFile.contentType} ${formatFile.pageTitle} has been created.`);
  });

  it('Open webform editor', () => {
    cy.get('p:contains("This webform has no elements added to it.")').should('exist');
    cy.get('a:contains("Please add elements to this webform.")').click();
    cy.get('a:contains("Add element")').should('exist');
  });

  it('Add Elements to the form', () => {
    editWebformElements(formatFile.webform);
  });

  it('Set State to Draft Approved State', () => {
    cy.get('body').then(() => {
      navigateToWebArea('Web Guide');
      cy.get('.views-table').find(`tr:contains("${formatFile.pageTitle}")`).find('a').first()
        .click();
    }).then(() => {
      cy.get('[data-drupal-selector="edit-new-state"]').first().select('Draft, approved');
      cy.get('[data-drupal-selector="edit-revision-log"]').type('Approved the draft');
    }).then(() => {
      cy.get('[data-drupal-selector="edit-submit"]').first().click();
      cy.get('.usa-alert__text').should('contain.text', 'The moderation state has been updated.');
    });
  });

  it('View the webpage', () => {
    cy.get('body').then(() => {
      cy.get('a:contains("View"):visible').first().click();
    }).then(() => {
      verifyPageElements(formatFile.expected);
    });
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
      cy.get('.usa-alert').should('contain.text', `${formatFile.contentType} ${formatFile.pageTitle} has been updated.`);
      verifyPageElements(formatFile.expectedEdited);
    });
  });

  it('Edit Webform', () => {
    cy.get('body').then(() => {
      cy.get('a:contains("Edit"):visible').first().click();
    }).then(() => {
      cy.get('.form-item-webform-link').find('a').invoke('removeAttr', 'target').click();
    }).then(() => {
      editWebformElements(formatFile.webformEdited);
    })
      .then(() => {
        cy.get('[data-drupal-selector="edit-actions"]').first().find('input').first()
          .click();
      })
      .then(() => {
        navigateToWebArea('Web Guide');
        cy.get('.views-table').find(`tr:contains("${formatFile.pageTitle}")`).find('a').first()
          .click();
      })
      .then(() => {
        verifyPageElements(formatFile.expectedEdited);
      });
  });

  it('Verify Dates in Page Source', () => {
    verifyPageSource('DC.date.created', getTodayDate('yyyy-MM-dd'));
    verifyPageSource('DC.date.modified', getTodayDate('yyyy-MM-dd'));
    verifyPageSource('DC.date.reviewed', getTodayDate('yyyy-MM-dd'), (60000 * 60 * 24 * 90));
  });

  it('Submit form without entering required fields', () => {
    cy.get('.webform-button--submit').click();
    cy.get('.usa-alert--error').should('contain.text', 'This is a checkbox field is required.');
    cy.get('.usa-alert--error').should('contain.text', 'First field is required.');
    cy.get('.usa-alert--error').should('contain.text', 'Middle field is required.');
    cy.get('.usa-alert--error').should('contain.text', 'Last field is required.');
  });

  it('Submit form after entering required fields', () => {
    cy.get('.form-item--checkbox:contains("This is a checkbox")').check();
    cy.get('.field--type-webform').find('.form-item--textfield:contains("First")').type('John');
    cy.get('.field--type-webform').find('.form-item--textfield:contains("Middle")').type('Jacob Jingleheimer');
    cy.get('.field--type-webform').find('.form-item--textfield:contains("Last")').type('Smith');
    cy.get('.webform-button--submit').click();
    cy.get('.webform-confirmation__message').should('contain.text', 'Thank you for contacting us.');
  });

  it('Verify form submission', () => {
    cy.get('body').then(() => {
      cy.get('a:contains("Back to form")').click();
      navigateToWebArea('Web Guide');
      cy.get('.views-table').find(`tr:contains("${formatFile.pageTitle}")`).find('a').first()
        .click();
    }).then(() => {
      cy.get('a:contains("Edit"):visible').first().click();
    }).then(() => {
      cy.get('.form-item-webform-link').find('a').invoke('removeAttr', 'target').click();
      cy.get('a:contains("Results")').click();
    })
      .then(() => {


        /*
All submissions (if any) will be visible
*/
      });
  });


  it('Publish the page', () => {
    cy.get('body').then(() => {
      cy.get('[data-drupal-selector="edit-new-state"]').first().select('Published');
      cy.get('[data-drupal-selector="edit-revision-log"]').type('Published the page');
      cy.get('[id=edit-workflow-508-compliant]').check({force: true});
    }).then(() => {
      cy.get('.box').find('[data-drupal-selector="edit-submit"]').first().click();
      cy.get('.usa-alert').should('contain.text', 'The moderation state has been updated.');
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
});
