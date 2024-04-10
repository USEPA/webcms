import {
  addPageSection,
  duplicatePageSection,
  getPageFields, getTodayDate, loginToDrupal, setPageFields, verifyPageElements,
} from '../../support/functions';

const formatFile = require('../../fixtures/eventsNew.json');

let pageTitle = 'old news';

describe('TC6 - Events', () => {
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
        pageTitle = 'This is a test event title';
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
*/

/*
 """All filled out metadata is available in source, including all date metadata (DC.date.modified, DC.date.created, and DC.date.reviewed).
  DC.date.reviewed date is 90 days later than DC.date.modified.

DC.type metadata defaults to Announcements and Schedules"""

Find and cancel a test event that you created. "Canceled" checkbox is displayed between the Date and Registration Deadline fields.
Selecting checkbox adds "Canceled:" to the left of the Event title

Find and delete a test event that you created. users can delete events that they created.
*/

/*    
    In selected Web area, click Create Content and select Public Notices Create Public Notice screen opens.  Web area field defaults to current selection
    Add Title is required
    Add Publication Date Can input manually date or with the calendar button
    Add content to Summary of Proposed Action Content can be input and is visible on view/save.
    Select Programs or Statutes from dropdown list Select one or more statutes. Cannot select None.
    Add Name and Address to Applicants or Respondents field 
    Select Proposed Actions from dropdown list Can select from list, add multiple items
    Select State or Territory from dropdown list Can choose from international regions, United states or manually input an " Other" location
    Add Docket Numbers Can input Docket numbers separated by comma and supporting links
    add FR Citations Can input FR citations separated by comma and supporting links
    Add Permit Number Can input Permit number
    Add Comments Due Date Input date manually or with the calendar button.
    Add Comments Extension Date Input date manually or with the calendar button.
    Fill out email, phone, and mail in the How to Comment Information appears in the how to comment box upon save
    Add media Media Library opens and the user can select or add media such as PDF and or video to the public notice
    Add hub links if required Used for easy navigations throught the EPA Webiste (visible at the top of the page after saving)
    Fill out description, type, etc in primary metadata Metadata output into HTML source on publication.
    Save page Page is saved and you're directed to view page.
    View page Content is visible, styles are rendered correctly and look good in Google Chrome and OK in IE
    Publish Prompted to enter log message for state change and verify Section 508 compliance. Page is published.  Log message is visible in revisions tab.  State/status on View tab, Revisions tab, and Content tab has changed to published. 
    View Revisions Tab "All revisions are listed in reverse chronological order. 
    
    Uses TC - 3: steps 2-6 to test revisions of this page"
    Review source code of published page. Note, you must be looking at the published revision, meaning the URL is the alias. All filled out metadata is available in source, including all date metadata (reviewed, modified, created). Date metadata matches type metadata.
    Go to Content tab on web area dashboard.  Find and delete a test Public notice that you created. Users can delete Public Notice that they created.
  */
