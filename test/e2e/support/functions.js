const {format} = require('date-fns');
const users = require('../fixtures/users.json');

cy.myproject = {
  makeUniqueUsername: () => `cypress-test-${getTodayDate('YYMMDD-HHmmss')}`,
};

//* *********************General Methods************************************** */
export function getTodayDate(dateFormat, offsetMs) {
  if (typeof offsetMs === 'undefined') {
    offsetMs = 0;
  }
  const nowTime = format(new Date(), 'MM.dd.yyyy HH:mm:ss');
  const msec = Date.parse(nowTime);
  const msecOffset = parseInt(msec, 10) + parseInt(offsetMs, 10);
  const offsetTime = format(new Date(msecOffset), dateFormat);
  return offsetTime;
}

export function getDateTime(dateFormat, offset, dateTimePortion) {
  if (typeof offset === 'undefined') {
    offset = 0;
  }
  const todayTimeOffset = getTodayDate(dateFormat, offset);
  const dateTimeOffset = todayTimeOffset.split(' ');
  const [relativeDate, relativeTime] = dateTimeOffset;
  let returnValue = dateTimeOffset;
  switch (dateTimePortion) {
  case 'date':
    returnValue = relativeDate;
    break;
  case 'time':
    returnValue = relativeTime;
    break;
  default:
    break;
  }
  return returnValue;
}


export function sortUnorderedList(vals, sortDescending) {
  vals.sort();
  if (sortDescending) { vals.reverse(); }
  return vals;
}

export function getRandomInt(min, max) {
  return Math.round(Math.random() * (max - min) + min);
}

export function getArrayOfRandomInts(len, min, max) {
  const arr = [];
  for (let x = 0; x < len; x += 1) {
    let newRand = getRandomInt(min, max);
    while (arr.includes(newRand)) {
      newRand = getRandomInt(min, max);
    }
    arr.push(newRand);
  }
  return arr;
}

export function createListOfPercentages(num) {
  const list = [];
  for (let x = 0; x < num + 1; x += 1) { list.push((100 / num) * x); }
  return list;
}

export function getArrayOfScrolls(numRows) {
  const arr = [];
  const numScrolls = numRows / 15;
  for (let x = 0; x < numScrolls; x += 1) {
    const per = x * (100 / numScrolls);
    arr.push(per);
  }
  arr.push(100);
  return arr;
}

export function countOccurencesInString(text, splitText) {
  const count = text.split(splitText).length;
  return count - 1;
}

export function findStringInArray(arr, substr) {
  let returnValue = false;
  if (arr.filter(str => str.includes(substr)).length > 0) {
    returnValue = true;
  }
  return returnValue;
}

//* ***********************************Login methods********** */
export function loginToDrupal(userNum) {
  cy.visit('/saml/login').then(() => {
    cy.get('body').then((pageBody) => {
      if (pageBody.find('button:contains("Login with User")').length > 0) {
        cy.wrap(pageBody).find('button:contains("Login with User")').click();
      }
      enterCredentials(userNum);
    });
  });
}

export function loginToDrupalUser(userNum) {
  cy.visit('/user').then(() => {
    cy.get('body').then((pageBody) => {
      if (pageBody.find('button:contains("Login with User")').length > 0) {
        cy.wrap(pageBody).find('button:contains("Login with User")').click();
      }

      enterCredentials(userNum);
    });
  });
}


export function enterCredentials(userNum) {
  let server = 'stg';
  if (Cypress.config().baseUrl.includes('prod')) { server = 'prod'; } else if (Cypress.config().baseUrl.includes('dev')) { server = 'dev'; }
  const currentUser = users.users[server][userNum];
  let userField = '[id=UserName]';
  let passField = '[id=Password]';
  let loginButton = '[value=Login]';

  cy.get('body').then((pageBody) => {
    if (pageBody.find('[id=edit-name]').length > 0) {
      userField = '[id=edit-name]';
      passField = '[id=edit-pass]';
      loginButton = '[id=edit-submit]';
    }
    cy.get(userField).click();
  }).then(() => {
    cy.get(userField).type(currentUser.username);
  }).then(() => {
    cy.get(passField).click();
  })
    .then(() => {
      cy.get(passField).type(currentUser.password);
    })
    .then(() => {
      cy.get(loginButton).click();
    });
}

export function logout(logoutMethod) {
  switch (logoutMethod) {
  case 'manage':
    cy.get('body').then((pageBody) => {
      if (pageBody.find('.toolbar-menu-administration').find('a:contains("Tools")').length === 0) {
        cy.get('a:contains("Manage")', {timeout: 120000}).click();
      }
    }).then(() => {
      cy.get('.toolbar-tray').contains('a', 'Tools').first().click({force: true});
      cy.get('.toolbar-tray').contains('a', 'Logout').first().click({force: true});
    });
    break;
  default:
    cy.get('body').then((pageBody) => {
      if (pageBody.find('.logout').length === 0) {
        cy.get('.toolbar-icon-user', {timeout: 120000}).click();
      }
    }).then(() => {
      cy.get('.logout').find('a').click();
    });
    break;
  }
}

//* **********************************New Content methods*************************************************** */
export function navigateToWebArea(webArea) {
  cy.get('nav').find('a:contains("My Web Areas")').first().click();
  cy.get('.block:contains("My Web Areas")').find(`a:contains("${webArea}"):visible`).first().click();
}

export function addContent(contentType) {
  cy.contains('a', 'Add new content').click();
  cy.get('.admin-list').contains('a', contentType).click();
  cy.get('.page-title').should('contain.text', `Add Web Area: Group node (${contentType})`);
}

export function verifyPageSource(searchTerm, expectedTerm) {
  const errors = [];
  cy.url().then((currentUrl) => {
    cy.request(currentUrl).its('body').then((content) => {
      const contentArray = content.split('\n');
      // cy.task('log',JSON.stringify(contentArray));
      const newarray = contentArray.filter(res => res.includes(searchTerm));
      cy.task('log', `\n${newarray}`);
      cy.wrap(newarray).each((currentItem) => {
        if (!currentItem.includes(expectedTerm)) {
          const errorJson = {};
          errorJson.expected = expectedTerm;
          errorJson.actual = currentItem;
          errors.push(errorJson);
        }
      });
      cy.task('log', `errors: ${errors}`);
      cy.wrap(errors).should('have.length', 0);
    });
  });
}


export function getErrorMessages() {
  const actualErrors = [];
  const currentErrors = Cypress.$('.messages--error');
  const errorList = currentErrors.text().split('\n');
  errorList.forEach((currentError) => {
    if ((!currentError.trim().includes('Error message')) && (currentError.trim().length > 0)) {
      actualErrors.push(currentError.trim());
    }
  });
  return actualErrors;
}

export function addPageSection(currentItem) {
  cy.task('log', `Adding ${currentItem.section} - ${currentItem.itemId}`);
  let expectedCount = 1;
  let actualCount = getElementCount(currentItem.itemId, currentItem.section);
  if (typeof currentItem.itemIndex !== 'undefined') {
    expectedCount = parseInt(currentItem.itemIndex, 10);
    expectedCount += 1;
  }
  if (actualCount < expectedCount) {
    if (['Body', 'Sidebar'].includes(currentItem.section)) {
      const beforeLabels = getElementCount(currentItem.itemId, currentItem.section);
      cy.get('body').then(() => {
        cy.get(`.placeholder:contains("${currentItem.section}"):visible`).parent().last().scrollIntoView();
      }).then(() => {
        cy.get(`.placeholder:contains("${currentItem.section}"):visible`).parent().then((addToSection) => {
          if (!addToSection.find('.dropbutton-wrapper').prop('class').includes('open')) {
            cy.wrap(addToSection).find('.dropbutton-toggle').find('button').click();
          }
        });
      }).then(() => {
        cy.get(`.placeholder:contains("${currentItem.section}"):visible`).parent().find(`[value="Add ${currentItem.itemName}"]:visible`).first()
          .click();
        // eslint-disable-next-line cypress/no-unnecessary-waiting
        cy.wait(2000);
      })
        .then(() => {
          verifyElementCount(currentItem.itemId, currentItem.section, beforeLabels + 1);
        });
    }
  }
}

export function verifyElementCount(currentElement, section, expectedCount) {
  if (['Body', 'Sidebar'].includes(section)) {
    cy.get(`table:contains("${section}")`).find(currentElement, {timeout: 60000}).should('have.length', expectedCount);
  } else {
    cy.get(currentElement).should('have.length', expectedCount);
  }
}


export function getElementCount(currentElement, section) {
  let elementCount = 0;
  if (['Body', 'Sidebar'].includes(section)) {
    elementCount = Cypress.$(`table:contains("${section}")`).find(currentElement).length;
  } else {
    elementCount = Cypress.$(currentElement).length;
  }
  cy.task('log', `Elements Found: ${elementCount}`);
  return elementCount;
}

export function duplicatePageSection(currentSection, currentItem, itemIndex = 0) {
  if ((typeof currentSection !== 'undefined') && (['Body', 'Sidebar'].includes(currentSection))) {
    let beforeLabels = 0;
    cy.get('body').then(() => {
      beforeLabels = getElementCount(currentItem, currentSection);
    }).then(() => {
      cy.get(`.field-label:contains("${currentSection}")`).parents('table').find(currentItem).eq(itemIndex)
        .find('.paragraphs-dropdown-toggle')
        .click();
    }).then(() => {
      cy.get(`.field-label:contains("${currentSection}")`).parents('table').find(currentItem).eq(itemIndex)
        .find('[value="Duplicate"]')
        .click();
      // eslint-disable-next-line cypress/no-unnecessary-waiting
      cy.wait(2000);
    })
      .then(() => {
        verifyElementCount(currentItem, currentSection, beforeLabels + 1);
      });
  }
}


export function removePageSection(currentItem) {
  cy.task('log', `Removing ${currentItem.section} - ${currentItem.itemId}`);
  if (typeof currentItem.itemIndex === 'undefined') {
    currentItem.itemIndex = 0;
  }
  if ((typeof currentItem.section !== 'undefined') && (['Body', 'Sidebar'].includes(currentItem.section))) {
    const beforeLabels = getElementCount(currentItem.itemId, currentItem.section);
    cy.get('body').then(() => {
      cy.get(`table:contains("${currentItem.section}")`).find(currentItem.itemId).find('.paragraphs-dropdown-toggle').first()
        .click();
    }).then(() => {
      cy.get(`table:contains("${currentItem.section}")`).find(currentItem.itemId).find('[value="Remove"]').first()
        .click();
    })
      .then(() => {
      // eslint-disable-next-line cypress/no-unnecessary-waiting
        cy.wait(1000);
      })
      .then(() => {
        cy.wrap(getElementCount(currentItem.itemId, currentItem.section)).should('eq', beforeLabels - 1);
      });
  }
}

export function setPageFields(currentItem) {
  if (typeof currentItem.itemIndex === 'undefined') {
    currentItem.itemIndex = 0;
  }
  if (typeof currentItem.fields !== 'undefined') {
    if (['Body', 'Sidebar'].includes(currentItem.section)) {
      cy.get(`table:contains("${currentItem.section}")`).find(currentItem.itemId).eq(currentItem.itemIndex).then((currentSection) => {
        cy.get('body').then(() => {
          cy.wrap(currentSection).scrollIntoView();
        }).then(() => {
          if (currentItem.fields.length > 0) {
            cy.wrap(currentItem.fields).each((currentField) => {
              if (typeof currentField.fieldIndex === 'undefined') {
                currentField.fieldIndex = 0;
              }
              if (currentField.fieldType === 'dialog') {
                cy.get('body').then(() => {
                  cy.wrap(currentSection).find(currentField.fieldName).eq(currentField.fieldIndex).click();
                  // eslint-disable-next-line cypress/no-unnecessary-waiting
                  cy.wait(2000);
                }).then(() => {
                  setupDialog(currentField, currentField.dialogId);
                });
              } else {
                setFieldValue(currentSection, currentField);
              }
            });
          }
        });
      });
    } else {
      cy.get(currentItem.itemId).eq(currentItem.itemIndex).then((currentPageSection) => {
        cy.get('body').then(() => {
          cy.wrap(currentPageSection).scrollIntoView();
        }).then(() => {
          if (currentItem.fields.length > 0) {
            cy.wrap(currentItem.fields).each((currentField) => {
              if (typeof currentField.fieldIndex === 'undefined') {
                currentField.fieldIndex = 0;
              }
              if (currentField.fieldType === 'dialog') {
                cy.get('body').then(() => {
                  cy.wrap(currentPageSection).find(currentField.fieldName).eq(currentField.fieldIndex).click();
                  // eslint-disable-next-line cypress/no-unnecessary-waiting
                  cy.wait(2000);
                }).then(() => {
                  setupDialog(currentField, currentField.dialogId);
                });
              } else {
                setFieldValue(currentPageSection, currentField);
              }
            });
          }
        });
      });
    }
  }
}

export function addWebformElements(webformElements) {
  cy.get('body').then(() => {
    cy.wrap(webformElements).each((currentElement) => {
      cy.get('a:contains("Add element")').click();
      cy.get(`tr:contains("${currentElement.elementType}")`).first().find('a:contains("Add element")').click();
      setWebformFields(currentElement);
    });
  }).then(() => {
    cy.get('[value="Save elements"]').click();
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(2000);
  });
}

export function setWebformFields(webformFields) {
  cy.wrap(webformFields.elementDetails).each((currentTab) => {
    cy.get('body').then(() => {
      cy.get('.webform-tabs-item-list').find(`a:contains("${currentTab.tabName}")`).click();
    }).then(() => {
      cy.wrap(currentTab.sections).each((currentSection) => {
        let currentElement = currentTab.tabId;
        if ((typeof currentSection.sectionId !== 'undefined') && (currentSection.sectionId.length !== 0)) {
          currentElement = currentSection.sectionId;
          cy.get(currentSection.sectionId).click();
        }
        cy.wrap(currentSection.fields).each((currentField) => {
          if (typeof currentField.fieldIndex === 'undefined') {
            currentField.fieldIndex = 0;
          }
          cy.get(currentElement).then((fieldElement) => {
            setFieldValue(fieldElement, currentField);
          });
        });
      });
    }).then(() => {
      cy.get('[id=drupal-off-canvas]').find('[data-drupal-selector=edit-submit]').click();
      cy.get(`tr:contains("${webformFields.elementName}")`).should('exist');
    });
  });
}

export function setFieldValue(currentElement, currentField) {
  cy.wrap(currentElement).find(currentField.fieldName).eq(currentField.fieldIndex).then((currentItem) => {
    switch (currentField.fieldType) {
    case 'select':
      cy.wrap(currentItem).select(currentField.fieldValue);
      // eslint-disable-next-line cypress/no-unnecessary-waiting
      cy.wait(2000);
      break;
    case 'checkbox':
      if (currentItem.prop('tagName').toLowerCase() === 'input') {
        if (currentField.fieldValue) {
          cy.wrap(currentItem).check();
        } else {
          cy.wrap(currentItem).uncheck();
        }
      } else if (currentField.fieldValue) {
        cy.wrap(currentItem).find('input').check();
      } else {
        cy.wrap(currentItem).find('input').uncheck();
      }
      break;
    case 'button':
    case 'radio':
      cy.wrap(currentItem, {timeout: 60000}).click();
      // eslint-disable-next-line cypress/no-unnecessary-waiting
      cy.wait(1000);
      break;
    case 'iframe':
      cy.wrap(currentItem).then((currentIframe) => {
        cy.wrap(currentIframe.contents()).find('body').type(`${currentField.fieldValue}{enter}`);
      });
      break;
    case 'javascript':
      cy.get('body').then(() => {
        cy.wrap(currentItem).scrollIntoView();
        cy.wrap(currentItem).click();
      }).then(() => {
        cy.wrap(currentField.fieldValue).each((jsCodeLine) => {
          cy.get('body').then(() => {
            cy.get('[id=edit-field-page-head-0-value]').type(jsCodeLine, {parseSpecialCharSequences: false}, {force: true});
          }).then(() => {
            cy.get('[id=edit-field-page-head-0-value]').type('{enter}');
          });
        });
      });

      break;
    case 'setValue':
      cy.wrap(currentItem).invoke('val', currentField.fieldValue);
      break;
    case 'autocomplete':
      cy.get('body').then(() => {
        cy.wrap(currentItem).type(currentField.fieldValue);
        // eslint-disable-next-line cypress/no-unnecessary-waiting
        cy.wait(1000);
      }).then(() => {
        cy.get('.ui-autocomplete:visible').find('a').first().click();
      });
      break;
    case 'date':
    case 'time':
    case 'datetime':
      cy.wrap(currentItem).type(getDateTime(currentField.fieldValue, currentField.offset, currentField.fieldType));
      break;
    default:
      cy.get('body').then(() => {
        cy.wrap(currentItem).click();
      }).then(() => {
        cy.wrap(currentItem).type(currentField.fieldValue);
        // eslint-disable-next-line cypress/no-unnecessary-waiting
        cy.wait(2000);
      });
      break;
    }
  });
}

export function setupDialog(dialogObject) {
  if (typeof dialogId === 'undefined') {
    dialogObject.dialogId = '.ui-dialog';
  }
  cy.get('body').then(() => {
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(2000);
    if (dialogObject.subFields.length > 0) {
      cy.wrap(dialogObject.subFields).each((currentSetting) => {
        cy.get(dialogObject.dialogId).then((currentDialog) => {
          if (currentDialog.find('iframe').length > 0) {
            cy.wrap(currentDialog).find('iframe').then((currentIframe) => {
              cy.wrap(currentIframe.contents()).then((iframeContents) => {
                cy.wrap(iframeContents).find('body').then((currentElement) => {
                  setFieldValue(currentElement, currentSetting);
                });
              });
            });
          } else {
            setFieldValue(currentDialog, currentSetting);
          }
        });
      });
    }
  });
}


export function getPageFields(currentItem) {
  let sectionElement = 'body';
  if (typeof currentItem.itemIndex === 'undefined') {
    currentItem.itemIndex = 0;
  }
  if (typeof currentItem.fields !== 'undefined') {
    if (['Body', 'Sidebar'].includes(currentItem.section)) {
      sectionElement = `table:contains("${currentItem.section}")`;
    }
    cy.wrap(currentItem.fields).each((currentField) => {
      if (typeof currentField.fieldIndex === 'undefined') {
        currentField.fieldIndex = 0;
      }
      cy.get(sectionElement).find(('.paragraph-type-label')).each((currentElement) => {
        let currentIndex = 0;
        if (currentElement.text().toLowerCase().includes(currentItem.itemName.toLowerCase())) {
          if (currentIndex === currentElement.itemIndex) {
            switch (currentField.fieldType) {
            case 'select':
              cy.wrap(currentElement).parents('tr').find(currentField.fieldName).eq(currentField.fieldIndex)
                .should('have.prop', 'selectedIndex', currentField.fieldDefault);
              break;
            case 'checkbox':
              if (currentField.fieldValue) {
                cy.wrap(currentElement).parents('tr').find(`.form-type-checkbox:contains("${currentField.fieldName}")`).find('input')
                  .eq(currentField.fieldIndex)
                  .should('have.prop', 'checked', currentField.fieldDefault);
              }
              break;
            case 'iframe':
              cy.wrap(currentElement).parents('tr').find('iframe').then((currentIframe) => {
                cy.wrap(currentIframe.contents()).find('body').eq(currentField.fieldIndex).should('contain.text', currentField.fieldValue);
              });
              break;
            default:
              cy.wrap(currentElement).parents('tr').find(currentField.fieldName).eq(currentField.fieldIndex)
                .should('have.value', currentField.fieldDefault);
              break;
            }
          } else {
            currentIndex += 1;
          }
        }
      });
    });
  }
}

export function verifyPageElements(expectedItems) {
  const errors = [];
  cy.get('body').then(() => {
    cy.wrap(expectedItems).each((currentItem) => {
      if (typeof currentItem.section === 'undefined') {
        currentItem.section = 'body';
      }
      cy.get(currentItem.section).then((currentSection) => {
        const foundItems = currentSection.find(currentItem.itemId).length;
        if (foundItems !== parseInt(currentItem.expectedCount, 10)) {
          currentItem.error = `expected count:${currentItem.expectedCount} - actual count: ${foundItems}`;
          errors.push(currentItem);
        }
        if (typeof currentItem.expectedProperties !== 'undefined') {
          cy.wrap(currentItem.expectedProperties).each((currentExpectedProperty) => {
            const actualPropertyValue = currentSection.find(currentItem.itemId)
              .prop(currentExpectedProperty.expectedPropertyName);
            if ((typeof actualPropertyValue === 'undefined') ||
            (!actualPropertyValue.toLowerCase().includes(currentExpectedProperty.expectedPropertyValue.toLowerCase()))) {
              currentItem.error = `property: ${currentExpectedProperty.expectedPropertyName} - expected: ${currentExpectedProperty.expectedPropertyValue} - actual: ${actualPropertyValue}`;
              errors.push(currentItem);
            }
          });
        }
      });
    });
  }).then(() => {
    cy.task('log', `errors: ${JSON.stringify(errors)}`);
    cy.wrap(errors).should('have.length', 0);
  });
}


export function selectTextInElement(currentElement, formatObject) {
  if (typeof formatObject.startSelection !== 'undefined') {
    cy.wrap(currentElement).find('.cke_wysiwyg_frame').then((currentIframe) => {
      cy.wrap(currentIframe.contents()).find('body').then((currentObject) => {
        cy.wrap(currentObject).setSelection(formatObject.startSelection, formatObject.endSelection);
        // eslint-disable-next-line cypress/no-unnecessary-waiting
        cy.wait(1000);
      });
    });
  }
}

export function setFormatting(currentObject, formatObject) {
  cy.task('log', JSON.stringify(formatObject));
  let dialogId = '.cke_dialog:visible';
  if (typeof formatObject.formatStatus === 'undefined') {
    formatObject.formatStatus = true;
  }
  cy.get('body').then(() => {
    selectTextInElement(currentObject, formatObject);
  }).then(() => {
    switch (formatObject.optionType) {
    case 'paragraph':
      cy.get('body').then(() => {
        cy.wrap(currentObject).find('.cke_toolbox').find('[title="Paragraph Format"]').then((paragraphFormat) => {
          if (paragraphFormat.parents('.cke_combo__format').attr('class').includes('cke_combo_off')) {
            cy.wrap(paragraphFormat).click();
          }
        });
      }).then(() => {
        cy.get('.cke_panel_frame').then((currentPanelIframe) => {
          cy.wrap(currentPanelIframe.contents()).find('body').each((iframeBody) => {
            cy.wrap(iframeBody).contains('.cke_panel_listItem', formatObject.formatType).click();
          });
        });
      });
      break;
    case 'style':
      cy.get('body').then(() => {
        cy.wrap(currentObject).find('.cke_toolbox').find('[title="Formatting Styles"]').then((paragraphFormat) => {
          if (paragraphFormat.parents('.cke_combo__styles').attr('class').includes('cke_combo_off')) {
            cy.wrap(paragraphFormat).click();
          }
        });
      }).then(() => {
        cy.get('.cke_panel_frame').then((currentPanelIframe) => {
          cy.wrap(currentPanelIframe.contents()).find('body').each((iframeBody) => {
            cy.wrap(iframeBody).contains('.cke_panel_listItem', formatObject.formatType).click();
          });
        });
      });
      break;
    case 'dialog':
      cy.get('body').then(() => {
        cy.wrap(currentObject).find('.cke_toolbox').find(`a:contains("${formatObject.formatType}")`).click();
      }).then(() => {
        // eslint-disable-next-line cypress/no-unnecessary-waiting
        cy.wait(3000);
      }).then(() => {
        cy.get('body').then((pageBody) => {
          if (pageBody.find('.ui-dialog:visible').length > 0) {
            dialogId = '.ui-dialog:visible';
          }
        });
      })
        .then(() => {
          if ((typeof formatObject.fields !== 'undefined') && (formatObject.fields.length > 0)) {
            cy.get(dialogId).then((currentDialog) => {
              if (currentDialog.find('iframe').length > 0) {
                cy.get(dialogId).find('iframe').then((currentPanelIframe) => {
                  cy.wrap(currentPanelIframe.contents()).then((iframeBody) => {
                    cy.wrap(formatObject.fields).each((currentSetting) => {
                      setDialogElement(iframeBody, currentSetting);
                    });
                  });
                });
              } else {
                cy.wrap(formatObject.fields).each((currentSetting) => {
                  cy.get(dialogId).then((currentDialogId) => {
                    setDialogElement(currentDialogId, currentSetting);
                  });
                });
              }
            });
          }
        });
      break;
    default:
      cy.wrap(currentObject).find('.cke_toolbox').find(`a:contains("${formatObject.formatType}")`).then((currentButton) => {
        const currentButtonClass = currentButton.attr('class');
        const click1 = (!formatObject.formatStatus) && (currentButtonClass.includes('cke_button_on'));
        const click2 = (formatObject.formatStatus) && (currentButtonClass.includes('cke_button_off'));
        if (click1 || click2) {
          cy.wrap(currentButton).click();
        }
      });
      break;
    }
  });
}

export function setDialogElement(currentObject, currentSetting) {
  if (typeof currentSetting.fieldIndex === 'undefined') {
    currentSetting.fieldIndex = 0;
  }
  switch (currentSetting.fieldType) {
  case 'select':
    cy.wrap(currentObject).find(currentSetting.fieldName).eq(currentSetting.fieldIndex).select(currentSetting.fieldValue);
    break;
  case 'checkbox':
    if (currentSetting.fieldValue) {
      cy.wrap(currentObject).find(currentSetting.fieldName).eq(currentSetting.fieldIndex).check();
    } else {
      cy.wrap(currentObject).find(currentSetting.fieldName).eq(currentSetting.fieldIndex).uncheck();
    }
    break;
  case 'media library item':
    cy.wrap(currentObject).find(`.media-library-item:contains("${currentSetting.fieldName}")`).eq(currentSetting.fieldIndex).find('input')
      .first()
      .click();
    break;
  case 'link':
    cy.wrap(currentObject).find(`a:contains("${currentSetting.fieldName}"):visible`).click();
    break;
  case 'click':
    cy.wrap(currentObject).find(currentSetting.fieldName).eq(currentSetting.fieldIndex).click();
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(2000);
    break;
  default:
    cy.get('body').then(() => {
      cy.wrap(currentObject).find(currentSetting.fieldName).eq(currentSetting.fieldIndex).click();
    }).then(() => {
      cy.wrap(currentObject).find(currentSetting.fieldName).eq(currentSetting.fieldIndex).clear();
    }).then(() => {
      cy.wrap(currentObject).find(currentSetting.fieldName).eq(currentSetting.fieldIndex).type(currentSetting.fieldValue);
    });
    break;
  }
}

export function verifyFormatting(currentObject, formatSetting) {
  if (typeof formatSetting.expectedCount === 'undefined') {
    formatSetting.expectedCount = 1;
  }
  if (formatSetting.formatType.toLowerCase().includes('special')) {
    cy.wrap(currentObject).find(`p:contains("${formatSetting.fields[0].fieldName}")`).should('have.length', formatSetting.expectedCount);
  } else if (typeof formatSetting.expectedText === 'undefined') {
    if (typeof formatSetting.expectedClass !== 'undefined') {
      cy.wrap(currentObject).find(`.${formatSetting.expectedClass}`).should('have.length', formatSetting.expectedCount);
    }
    if (typeof formatSetting.expectedTag !== 'undefined') {
      cy.wrap(currentObject).find(formatSetting.expectedTag).should('have.length', formatSetting.expectedCount);
    }
  } else {
    if (typeof formatSetting.expectedClass !== 'undefined') {
      cy.wrap(currentObject).find(`.${formatSetting.expectedClass}:contains("${formatSetting.expectedText}")`).should('have.length', formatSetting.expectedCount);
    }
    if (formatSetting.expectedTag === 'hr') {
      cy.wrap(currentObject).find(formatSetting.expectedTag).should('have.length', formatSetting.expectedCount);
    } else if (typeof formatSetting.expectedTag !== 'undefined') {
      cy.wrap(currentObject).find(`${formatSetting.expectedTag}:contains("${formatSetting.expectedText}")`).should('have.length', formatSetting.expectedCount);
    }
  }
}


//* *****************************************************Performance Methods**************************************** */

export function testPageLoad(url, expectedTime, commands) {
  cy.visit(url, {timeout: 60000,
    onBeforeLoad: (win) => {
      win.performance.mark('start-loading');
    }})
    .its('performance').then((currentPerformance) => {
      cy.get('body')
        .then(() => currentPerformance.mark('end-loading'))
        .then(() => {
          currentPerformance.measure('pageLoad', 'start-loading', 'end-loading');
          // Retrieve the timestamp we just created
          const measure = currentPerformance.getEntriesByName('pageLoad')[0];
          // This is the total amount of time (in milliseconds) between the start and end
          const {duration} = measure;
          for (let x = 0; x < commands.length; x += 1) {
            const commandJsonItem = JSON.parse(JSON.stringify(commands[x]));
            if (commandJsonItem.elapsed >= 0) {
              assert.isAtMost(commandJsonItem.elapsed, expectedTime, `${commandJsonItem.name} exceeded the expected time: ${expectedTime}`);
            }
          }
          // assert.isAtMost(duration, expectedTime);
        });
    });
}

//* ********************************************************Iframe methods***************************************** */
export function getCurrentIframe(currentElement) {
  return cy.wrap(currentElement.contents()).find('body');
}

export function printIframeText(currentElement) {
  cy.wrap(currentElement).then((currentIframe) => {
    const theiframe = getCurrentIframe(currentIframe);
    cy.task('log', `iframe text: ${theiframe.text()}`);
  });
}

export function getIframeDocument() {
  return cy.get('iframe').first().should(iframe => expect(iframe.contents().find('body').to.exist)).then((currentIframe) => {
    const pageBody = currentIframe.contents().find('body');
    cy.task('log', pageBody);
  })
    .within({}, () => {
      cy.get('div').should('exist');
    });
  // .its('0.contentDocument').should('exist')
}

export function getIframeBody() {
  return getIframeDocument();
}
