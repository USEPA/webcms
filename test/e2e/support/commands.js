require('cypress-downloadfile/lib/downloadFileCommand');
require('cypress-audit/commands');
require('cy-verify-downloads').addCustomCommand();
require('cypress-iframe');

/*
Cypress.Commands.add('iframe', { prevSubject: 'element' }, $iframe => {
    return new Cypress.Promise(resolve => {
        $iframe.ready(function() {
          resolve($iframe.contents().find('body'));
        });
    });
  });
*/
Cypress.Commands.add('switchToIframe', (iframe) => {
  return cy.get(iframe).its('0.contentDocument.body').should('be.visible').then(cy.wrap);
});


/**
 * Credits
 * @Bkucera: https://github.com/cypress-io/cypress/issues/2839#issuecomment-447012818
 * @Phrogz: https://stackoverflow.com/a/10730777/1556245
 *
 * Usage
 * ```
 * // Types "foo" and then selects "fo"
 * cy.get('input')
 *   .type('foo')
 *   .setSelection('fo')
 *
 * // Types "foo", "bar", "baz", and "qux" on separate lines, then selects "foo", "bar", and "baz"
 * cy.get('textarea')
 *   .type('foo{enter}bar{enter}baz{enter}qux{enter}')
 *   .setSelection('foo', 'baz')
 *
 * // Types "foo" and then sets the cursor before the last letter
 * cy.get('input')
 *   .type('foo')
 *   .setCursorAfter('fo')
 *
 * // Types "foo" and then sets the cursor at the beginning of the word
 * cy.get('input')
 *   .type('foo')
 *   .setCursorBefore('foo')
 *
 * // `setSelection` can alternatively target starting and ending nodes using query strings,
 * // plus specific offsets. The queries are processed via `Element.querySelector`.
 * cy.get('body')
 *   .setSelection({
 *     anchorQuery: 'ul > li > p', // required
 *     anchorOffset: 2 // default: 0
 *     focusQuery: 'ul > li > p:last-child', // default: anchorQuery
 *     focusOffset: 0 // default: 0
 *    })
 */

// Low level command reused by `setSelection` and low level command `setCursor`

Cypress.Commands.add('selection', {prevSubject: true}, (subject, fn) => {
  cy.wrap(subject)
    .trigger('mousedown', {force: true})
    .then(fn)
    .trigger('mouseup', {force: true});

  cy.document().trigger('selectionchange');
  return cy.wrap(subject);
});

Cypress.Commands.add('preserveAllCookiesOnce', () => {
  cy.getCookies().then(cookies => {
    const namesOfCookies = cookies.map(c => c.name);
    Cypress.Cookies.preserveOnce(...namesOfCookies);
  });
});

Cypress.Commands.add('setSelection', {prevSubject: true}, (subject, query, endQuery) => {
  return cy.wrap(subject).selection(($el) => {
    if (typeof query === 'string') {
      const anchorNode = getTextNode($el[0], query);
      const focusNode = endQuery ? getTextNode($el[0], endQuery) : anchorNode;
      const anchorOffset = anchorNode.wholeText.indexOf(query);
      const focusOffset = endQuery
        ? focusNode.wholeText.indexOf(endQuery) + endQuery.length
        : anchorOffset + query.length;
      setBaseAndExtent(anchorNode, anchorOffset, focusNode, focusOffset);
    } else if (typeof query === 'object') {
      const el = $el[0];
      const anchorNode = getTextNode(el.querySelector(query.anchorQuery));
      const anchorOffset = query.anchorOffset || 0;
      const focusNode = query.focusQuery
        ? getTextNode(el.querySelector(query.focusQuery)) :
        anchorNode;
      const focusOffset = query.focusOffset || 0;
      setBaseAndExtent(anchorNode, anchorOffset, focusNode, focusOffset);
    }
  });
});


// Low level command reused by `setCursorBefore` and `setCursorAfter`, equal to `setCursorAfter`

Cypress.Commands.add('setCursor', {prevSubject: true}, (subject, query, atStart) => {
  return cy.wrap(subject).selection(($el) => {
    const node = getTextNode($el[0], query);
    const offset = node.wholeText.indexOf(query) + (atStart ? 0 : query.length);
    const currentDocument = node.ownerDocument;
    currentDocument.getSelection().removeAllRanges();
    currentDocument.getSelection().collapse(node, offset);
  });
});

Cypress.Commands.add('setCursorBefore', {prevSubject: true}, (subject, query) => {
  cy.wrap(subject).setCursor(query, true);
});

Cypress.Commands.add('setCursorAfter', {prevSubject: true}, (subject, query) => {
  cy.wrap(subject).setCursor(query);
});

// Helper functions

function getTextNode(el, match) {
  const walk = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null, false);
  if (!match) {
    return walk.nextNode();
  }

  let node;
  while ((node = walk.nextNode())) {
    if (node.wholeText.includes(match)) {
      return node;
    }
  }
}

function setBaseAndExtent(...args) {
  const currentDocument = args[0].ownerDocument;
  currentDocument.getSelection().removeAllRanges();
  currentDocument.getSelection().setBaseAndExtent(...args);
}
