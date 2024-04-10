import {
  addPageSection,
  duplicatePageSection,
  getPageFields, getTodayDate, loginToDrupal, setPageFields, verifyPageElements,
} from '../../support/functions';

const formatFile = require('../../fixtures/regulationNew.json');

let pageTitle = 'old news';

describe('TC6 - Regulations', () => {
  Cypress.on('uncaught:exception', (_err, _runnable) => false);
  before(() => {
    loginToDrupal('first');
  });

  beforeEach(() => {
    cy.preserveAllCookiesOnce();
    cy.wrap(pageTitle).as('pageTitle');
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
      cy.get('.field--name-title').find('input').then((titleField) => {
        // pageTitle = titleField.val().trim();
        pageTitle = 'This is a test Regulation title';
        cy.wrap(pageTitle).as('pageTitle');
      });
    }).then(() => {
      cy.get('[data-drupal-selector="edit-actions"]').first().find('input').first()
        .click({force: true});
    }).then(() => {
      cy.get('.usa-alert--success').find('.usa-alert__text').should('contain.text', `${formatFile.contentType} ${pageTitle} has been created.`);
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
      cy.get('.usa-alert__text').should('contain.text', `${formatFile.contentType} ${pageTitle} has been updated.`);
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
      cy.get('.views-table').find(`tr:contains("${pageTitle}")`).find('.views-field-moderation-state').should('contain.text', 'Published');
      cy.get('.views-table').find(`tr:contains("${pageTitle}")`).find('.views-field-moderation-state-1').should('contain.text', 'Published');
    });
  });

  it('Published Content tab shows the page as published', () => {
    cy.get('body').then(() => {
      cy.get('a:contains("Published Content"):visible').first().click();
    }).then(() => {
      cy.get('.views-table').find(`tr:contains("${pageTitle}")`).find('.views-field-moderation-state').should('contain.text', 'Published');
    });
  });

  it('Clone the page', () => {
    cy.get('body').then(() => {
      cy.get(`a:contains("${pageTitle}"):visible`).first().click();
      cy.get('a:contains("Clone"):visible').first().click();
      cy.get('[value="Clone"]').first().click();
    }).then(() => {
      cy.get('.usa-alert__text').should('contain.text', `The entity ${pageTitle}`);
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
      cy.get('.views-table').find(`tr:contains("Cloned: ${pageTitle}")`).find('.views-field-moderation-state').should('contain.text', 'Draft');
      cy.get('.views-table').find(`tr:contains("Cloned: ${pageTitle}")`).find('.views-field-moderation-state-1').should('contain.text', 'Draft');
    });
  });

  it('Published Content tab does not show the cloned page', () => {
    cy.get('body').then(() => {
      cy.get('a:contains("Published Content"):visible').first().click();
    }).then(() => {
      cy.get('.views-table').find(`tr:contains("Cloned: ${pageTitle}")`).should('not.exist');
    });
  });

  it('Return to View Page', () => {
    cy.get('body').then(() => {
      cy.get('a:contains("Latest Revisions")').click();
    }).then(() => {
      cy.get('.views-table').find('tbody').find('a').each((currentLink) => {
        if (currentLink.text().toLowerCase() === pageTitle.toLowerCase()) {
          cy.wrap(currentLink).click();
          return false;
        }
      });
    }).then(() => {
      verifyPageElements(formatFile.expected);
      verifyPageElements(formatFile.expectedEdited);
    });
  });
});


/*
 """All filled out metadata is available in source, including all date metadata (DC.date.modified, DC.date.created, and DC.date.reviewed).
  DC.date.reviewed date is 90 days later than DC.date.modified.

DC.type metadata defaults to Announcements and Schedules"""

Find and cancel a test event that you created. "Canceled" checkbox is displayed between the Date and Registration Deadline fields.
Selecting checkbox adds "Canceled:" to the left of the Event title

Find and delete a test event that you created. users can delete events that they created.

    In selected Web area, click Create Content and select Regulation	Create Regulation Page screen opens. 
    (As super admin, web area field will default to current selection.)
Add Regulation Title	is required
Add Effective Date and End Date	"Date can be selected from calendar or entered manually. Validates as mm-dd-yyyy
End date must be greater than start date."
Add content to Rule Summary, Rule History, Additional Resources and Compliance either by direct input or copy-and-paste WYSIWYG buttons.	"Content can be input and is visible on the page. Rule Summary is a required field

Chrome does not support paste with the wysiwyg editor - must use ctrl-v"
Add Primary Metadata. Add more than 256 characters to the Description metadata.	"User inputs well-formatted metadata into the following fields: Description, Keywords, Channel, Type.

Description is now limited to 256 characters. If you edit an existing page, and the description is more than 256 characters, you will be prompted to reduce the number. Keywords are no longer required.

Metadata output into HTML source on publication."
Save and view page	Content is visible, styles are rendered correctly and look good in Google Chrome and OK in IE 8. Contact Us links, hublinks (or sidebar), breadcrumbs (for MS) are visible. Code of Federal Regulations and Docket Number values are linked if a link was added. Internal menu links work.
"Edit draft and add additional entries for either Legal Authority, Federal Register Citiation, Code of Federal Regulations, Docket Number, and Effective Date. 

Save page."	Able to add more than one item for each field. Items are visible after saving.
Select the Collapse button in one of the citation boxes. 	The box shrinks and the collapse button becomes the edit button
Select the three dots next to the collapse/edit button and select duplicate	Another citation appears - with the same citation info
Use Remove option in drop-down next to collapse/edit button to remove items for Code of Federal Regulations, Docket Number, Legal Authority, and Federal Register Citation fields.	"Item is removed. 

(Effective Date does not have a remove button)"
"Remove all data from the following optional fields:

Legal Authority, Federal Register Citation, Code of Federal Regulations, Docket Number, and Effective Date.  Save and check how the page looks."	The system should not render the "Basic Information" right-side box or header.
Publish	Prompted to enter log message for state change and verify Section 508 compliance. Page is published. Log message is visible in Revisions tab. State/status on View tab, Workflow tab, and Dashboard > Content tab has changed to published. Current revision is "published," all other revisions are draft.
Go to content tab on the web area group dashboard and select Latest Revisions	You can filter by title, content type, author, and moderation state
Select your page and then one of the options from the Actions pull-down menu. (e.g. Set to Published)	Page changed moderation state (e.g. published) depending on what action you selected. 
Review source code of published page. Note, you must be looking at the published revision, meaning the URL is the alias.	All filled out metadata is available in source, including all date metadata (reviewed, modified, created). Date metadata matches type metadata.

*/
