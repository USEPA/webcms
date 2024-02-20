const {loginToDrupal, logout} = require('../../support/functions');

describe('TC 0 - SSO', () => {
  Cypress.on('uncaught:exception', (_err, runnable) => false);
  before(() => {
  });

  it('Can login with username successfully', () => {
    loginToDrupal('first');
  });

  it('Can Log out from Manage menu', () => {
    cy.get('body').then(() => {
      logout('manage');
    }).then(() => {
      cy.visit('/saml/login');
    }).then(() => {
      cy.get('button:contains("Login with User")').should('exist');
    });
  });

  it('Can Log out from username dropdown', () => {
    cy.get('body').then(() => {
      loginToDrupal('first');
    }).then(() => {
      logout('user');
    }).then(() => {
      cy.visit('/saml/login');
    })
      .then(() => {
        cy.get('button:contains("Login with User")').should('exist');
      });
  });

  it('Log into published page', () => {
    cy.visit('/user/login?destination=webguide/epa-web-training-classes').then(() => {
      cy.get('[id=edit-name]').should('exist');
    });
  });

  it('Log into unpublished page', () => {
    cy.visit('/user/login?destination=osa/scientific-integrity-poster').then(() => {
      cy.get('[id=edit-name]').should('exist');
    });
  });
});
