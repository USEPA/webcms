import {
  addPageSection, getTodayDate, loginToDrupal, setPageFields,
} from '../../support/functions';

const formatFile = require('../../fixtures/revisions.json');

const pages = [formatFile.revisionTest, formatFile.bulkPublishTest];

describe(formatFile.testCaseName, () => {
  Cypress.on('uncaught:exception', (_err, _runnable) => false);
  before(() => {
    cy.get('body').then(() => {
      loginToDrupal('first');
    }).then(() => {
      cy.wrap(pages).each((currentPage) => {
        cy.get('body').then(() => {
          cy.get('nav').find('a:contains("My Web Areas")').first().click();
          cy.get('.block:contains("My Web Areas")').find('a:contains("Web Guide"):visible').first().click();
          cy.contains('a', 'Add new content').click();
          cy.get('.admin-list').contains('a', formatFile.contentType).click();
        }).then(() => {
          cy.wrap(currentPage).each((currentItem) => {
            addPageSection(currentItem);
            setPageFields(currentItem);
          });
        }).then(() => {
          cy.get('[data-drupal-selector="edit-submit"]').first().click();
        })
          .then(() => {
            cy.get('a:contains("Edit"):visible').first().click();
          })
          .then(() => {
            cy.wrap(formatFile.editedItems).each((currentEdit) => {
              setPageFields(currentEdit);
            });
          })
          .then(() => {
            cy.get('[data-drupal-selector="edit-submit"]').first().click();
          })
          .then(() => {
            cy.get('[data-drupal-selector="edit-new-state"]').first().select('Draft, approved');
            cy.get('[data-drupal-selector="edit-revision-log"]').type('Approved the draft');
          });
      });
    });
  });

  beforeEach(() => {
    cy.preserveAllCookiesOnce();
  });

  it('Verify revision tab', () => {
    let previousDateTime = '';
    cy.get('body').then(() => {
      cy.get('.toolbar-bar').find('a:contains("My Web Areas")').click();
      cy.get('.block:contains("My Web Areas")').find('a:contains("Web Guide"):visible').first().click();
      cy.get('.tabs').find('a:contains("Latest Revisions")').click();
    }).then(() => {
      cy.get('.views-table').find('tbody').find('tr:contains("Revisions Test")').first()
        .find('a')
        .first()
        .click();
      cy.get('.button-group').find('a:contains("Revisions")').click();
      cy.get('.diff-revisions').find('thead').find('th:contains("Revision")').should('be.visible');
      cy.get('.diff-revisions').find('thead').find('th:contains("Operations")').should('be.visible');
    }).then(() => {
      cy.get('.diff-revisions').find('tbody').find('tr').eq(0)
        .then((latest) => {
          cy.wrap(latest).should('have.class', 'revision-latest');
          previousDateTime = latest.text();
        });
    })
      .then(() => {
        cy.get('.diff-revisions').find('tbody').find('tr').each((currentRevision) => {
          cy.wrap(currentRevision).should('contain.text', getTodayDate('MMM-dd-yy'));
        });
      });
  });

  it('Compare Revisions', () => {
    cy.get('body').then(() => {
      cy.get('.diff-revisions').find('tbody').find('tr').last()
        .find('input')
        .first()
        .click();
    }).then(() => {
      cy.get('[value="Compare selected revisions"]').first().click();
    }).then(() => {
      cy.get('.diffmod', {timeout: 60000}).first().should('contain.text', 'second body html -edited');
    });
  });

  it('Revert to previous revision', () => {
    cy.get('body').then(() => {
      cy.get('.toolbar-bar').find('a:contains("My Web Areas")').click();
      cy.get('.block:contains("My Web Areas")').find('a:contains("Web Guide"):visible').first().click();
      cy.get('.tabs').find('a:contains("Latest Revisions")').click();
    }).then(() => {
      cy.get('.views-table').find('tbody').find('tr:contains("Revisions Test")').first()
        .find('a')
        .first()
        .click();
      cy.get('.button-group').find('a:contains("Revisions")').click();
    }).then(() => {
      cy.get('a:contains("Copy and Set as Latest Revision")').last().click();
    })
      .then(() => {
        cy.get('[value="Copy and Set as Latest Revision"]').last().click();
        cy.get('.messages').should('contain.text', 'Basic page Revisions Test has been reverted to the revision from');
        cy.get('.diff-revisions').find('tbody').find('tr').eq(0)
          .should('contain.text', 'Copy of the revision from');
      });
  });

  it('Revert to original revision', () => {
    cy.get('body').then(() => {
      cy.get('tbody').find('tr:contains("Made an edit")').first().find('a:contains("Copy and Set as Latest Revision")')
        .click();
    }).then(() => {
      cy.get('[value="Copy and Set as Latest Revision"]').last().click();
      cy.get('.messages').should('contain.text', 'Basic page Revisions Test has been reverted to the revision from');
    });
  });

  it('Set State to Draft Approved State', () => {
    cy.get('body').then(() => {
      cy.get('.diff-revisions').find('tbody').find('tr').first()
        .find('a')
        .first()
        .click();
      cy.get('[id="edit-new-state"]').first().select('Draft, approved');
      cy.get('[id="edit-revision-log"]').type('Approved the draft');
    }).then(() => {
      cy.get('[data-drupal-selector="edit-submit"]').click();
      cy.get('.usa-alert__text').should('contain.text', 'The moderation state has been updated.');
    });
  });

  it('Publish the page', () => {
    cy.get('body').then(() => {
      cy.get('[id="edit-new-state"]').first().select('Published');
      cy.get('[id="edit-revision-log"]').type('Published the page');
      cy.get('[id=edit-workflow-508-compliant]').check({force: true});
    }).then(() => {
      cy.get('[data-drupal-selector="edit-submit"]').click();
      cy.get('.usa-alert__text').should('contain.text', 'The moderation state has been updated.');
      cy.get('.field--tight:contains("Moderation state")').should('contain.text', 'Published');
      cy.get('.js-toggle-admin-content').find('a:contains("Revisions")').click();
      cy.get('.diff-revisions').find('tbody').find('tr').eq(0)
        .should('contain.text', 'Published the page (Published)');
      cy.get('.diff-revisions').find('tbody').find('tr').eq(0)
        .should('contain.text', 'Currently published revision');
    });
  });

  it('Edit a published page', () => {
    cy.get('body').then(() => {
      cy.get('.tabs', {timeout: 60000}).find('a:contains("View")').click();
      cy.get('a:contains("Edit"):visible', {timeout: 60000}).first().click();
    }).then(() => {
      cy.wrap(formatFile.editedItems2).each((currentEdit) => {
        setPageFields(currentEdit);
      });
    }).then(() => {
      cy.get('[data-drupal-selector="edit-submit"]').first().click();
    })
      .then(() => {
        cy.get('.button-group').find('a:contains("Revisions")').click();
        cy.get('.diff-revisions').find('tbody').find('tr').eq(0)
          .should('contain.text', 'Edit Published Page (Draft)');
        cy.get('.diff-revisions').find('tbody').find('tr').eq(0)
          .should('contain.text', 'Latest revision');
        cy.get('.diff-revisions').find('tbody').find('tr').eq(1)
          .should('contain.text', 'Currently published revision');
      });
  });

  it('Unpublish a published page', () => {
    cy.get('body').then(() => {
      cy.get('.block').find('a:contains("View")').click();

      cy.get('[id="edit-new-state"]').first().select('Unpublished');
      cy.get('[id="edit-revision-log"]').type('Unpublish a published page');
      cy.get('[value=Apply]').click();
    }).then(() => {
      cy.get('.field:contains("Moderation state")').should('contain.text', 'Draft');
    }).then(() => {
      cy.get('.button-group').find('a:contains("Revisions")').click();
      cy.get('.diff-revisions').find('tbody').find('tr').eq(0)
        .should('contain.text', 'Restoring the latest revision');
      cy.get('.diff-revisions').find('tbody').find('tr').eq(1)
        .should('contain.text', 'Unpublish a published page (Unpublished)');
      cy.get('.diff-revisions').find('tbody').find('tr').eq(0)
        .should('contain.text', 'Latest revision');
      cy.get('.diff-revisions').find('tbody').find('tr').eq(0)
        .should('contain.text', 'Current revision');
    });
  });

  it('Delete a Revision', () => {
    cy.get('body').then(() => {
      cy.get('.diff-revisions').find('tbody').find('tr:contains("Unpublish a published page")').should('exist');
    }).then(() => {
      cy.get('.diff-revisions').find('tbody').find('tr:contains("Unpublish a published page")').first()
        .find('.dropbutton-toggle')
        .first()
        .find('button')
        .first()
        .click();
      cy.get('a:contains("Delete"):visible').first().click();
    }).then(() => {
      cy.get('.form-actions').find('[value="Delete"]').should('have.length.gte', 1);
      cy.get('.form-actions').find('[value="Delete"]').click();
    })
      .then(() => {
        cy.get('.button-group').find('a:contains("Revisions")').click();
      })
      .then(() => {
        cy.get('.diff-revisions').find('tbody').find('tr:contains("Unpublish a published page")').should('not.exist');
      });
  });


  it('Bulk Publish', () => {
    cy.get('body').then(() => {
      cy.get('nav').find('a:contains("My Web Areas")').first().click();
      cy.get('.block:contains("My Web Areas")').find('a:contains("Web Guide"):visible').first().click();
    }).then(() => {
      cy.get('[id="edit-moderation-state-1"]').select('Draft');
      cy.get('[value=Filter]').click();
    }).then(() => {
      cy.get('tr:contains("Revisions Test")').first().find('input').first()
        .check();
      cy.get('tr:contains("Bulk Publish Test")').first().find('input').first()
        .check();
    })
      .then(() => {
        cy.get('.form-type-select:contains("Action")').find('select').select('Set to Published');
        cy.get('.js-form-item-workflow-508-compliant').find('input').check();
        cy.get('[value="Apply to selected items"]').first().click();
      })
      .then(() => {
        cy.get('.messages').should('contain.text', 'Set to Published was applied to 2 items.');
        cy.get('[value=Reset]').click();
      })
      .then(() => {
        cy.get('nav').find('a:contains("My Web Areas")').first().click();
        cy.get('.block:contains("My Web Areas")').find('a:contains("Web Guide"):visible').first().click();
        cy.get('tbody').find('tr:contains("Revisions Test")').first().find('.views-field-moderation-state')
          .should('contain.text', 'Published');
        cy.get('tbody').find('tr:contains("Revisions Test")').first().find('.views-field-moderation-state-1')
          .should('contain.text', 'Published');
        cy.get('tbody').find('tr:contains("Bulk Publish Test")').first().find('.views-field-moderation-state')
          .should('contain.text', 'Published');
        cy.get('tbody').find('tr:contains("Bulk Publish Test")').first().find('.views-field-moderation-state-1')
          .should('contain.text', 'Published');
      });
  });
});
