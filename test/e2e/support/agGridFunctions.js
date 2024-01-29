import {
  convertToScienticNotation,
  createListOfPercentages,
  getArrayOfRandomInts,
  getArrayOfScrollsFromValue,
} from './commonFunctions';
import {readValueFromJsonFile, writeToFile} from './fileFunctions';

const horizontalScrolls = ['left', 'center', 'right'];
const verticalScrolls = ['top', 'center', 'bottom'];

//* ***************Sorting Table************************************** */
export function sortColumn(columnId, ascending, tableNum) {
  if (typeof tableNum === 'undefined') { tableNum = 0; }
  let sortCorrect = false;
  const times = [1, 2, 3];
  cy.wrap(times).each(() => {
    scrollToColumn(columnId, 'id');
    if (ascending) {
      cy.get('div.ag-header-row').find(`[col-id=${columnId}]`).find('.ag-sort-ascending-icon').then((sortAscendingIcon) => {
        const currentClass = sortAscendingIcon.attr('class');
        if (!currentClass.includes('ag-hidden')) {
          sortCorrect = true;
          return false;
        }
        cy.get('div.ag-header-row').find(`[col-id=${columnId}]`).click();
      });
    } else {
      cy.get('div.ag-header-row').find(`[col-id=${columnId}]`).find('.ag-sort-descending-icon')
        .then((sortDescendingIcon) => {
          const currentClass = sortDescendingIcon.attr('class');

          if (!currentClass.includes('ag-hidden')) {
            sortCorrect = true;
            return false;
          }
          cy.get('div.ag-header-row').find(`[col-id=${columnId}]`).click();
        });
    }

    if (sortCorrect) {
      return false;
    }
  });
}

export function isTableSorted(columnId, ascending, sortAsString) {
  let actual = [];
  let expected = [];
  cy.get('.ag-center-cols-container .ag-row').then((rows) => {
    rows.sort((a, b) => +a.getAttribute('row-index') - +b.getAttribute('row-index'));
  }).then((sortedRows) => {
    cy.wrap(sortedRows).each((sortedRow) => {
      let currentValue = sortedRow.find(`[col-id=${columnId}]`).text().replace(/\s\s+/g, ' ').trim();
      if (!sortAsString) {
        currentValue = currentValue.replace('mg/kg/day', '').trim();
        if (currentValue === '-') {
          currentValue = '';
        }
      }
      if (currentValue.length > 0) {
        actual.push(currentValue);
      }
    });
  }).then(() => {
    if (columnId.includes('preferred') || columnId.includes('Auto')) {
      const filtered = actual.filter(value => value.length > 0);
      actual = filtered;
    } else {
      let start = 0;
      let stopIndex = actual.length;
      let arr = [];
      cy.wrap(actual).each((actualValue, index) => {
        if (actualValue.length === 0 || index === (actual.length - 1)) {
          stopIndex = index + 1;
          arr = actual.slice(start, stopIndex);

          if (sortAsString) {
            expected = arr.slice().sort();

            if (!ascending) {
              expected = expected.reverse();
            }
          } else {
            expected = arr.slice().sort((a, b) => a - b);
            if (!ascending) {
              expected = expected.reverse();
            }
          }

          cy.task('log', `actual section: ${arr}\nexpected:       ${expected}`);
          cy.wrap(arr).should('deep.equal', expected);
          start = stopIndex + 1;
          stopIndex = actual.length;
        }
      });
    }
  });
}

//* *********************************Header Methods************************************** */

export function checkTableHeaders(expectedHeaders, locType, tableNum) {
  if (typeof locType === 'undefined') {
    locType = 'id';
  }
  if (typeof tableNum === 'undefined') {
    tableNum = 0;
  }

  cy.wrap(expectedHeaders).each((expectedHeader) => {
    scrollToColumn(expectedHeader, locType);
    if (locType === 'name') {
      cy.get('.ag-header-viewport').eq(tableNum).contains('.ag-header-cell', expectedHeader).should('exist');
    } else {
      cy.get('.ag-header-viewport').eq(tableNum).find(`[col-id=${expectedHeader}]`).should('exist');
    }
  });

  scrollHorizontally('left');
}

export function getTableColumns(idType) {
  let columns = [];
  scrollHorizontally('left');
  let headerCount = Cypress.$('.ag-header-row').find('.ag-header-cell').length;
  for (let x = 0; x < headerCount; x += 1) {
    if (idType === 'name') {
      columns.push(Cypress.$('.ag-header-row').find('.ag-header-cell').eq(x).text()
        .replace(/\s\s+/g, ' ')
        .trim());
    } else {
      columns.push(Cypress.$('.ag-header-row').find('.ag-header-cell').eq(x).attr('col-id'));
    }
  }
  return columns;
}

//* *********************************Scroll Methods************************************* */

export function scrollToColumn(searchTerm, valueType, tableNum, paneName) {
  cy.task('log', `Scrolling to ${searchTerm}`);
  if (typeof tableNum === 'undefined') {
    tableNum = 0;
  }
  if (typeof paneName === 'undefined') {
    paneName = 'center';
  }
  if (typeof valueType === 'undefined') {
    valueType = 'id';
  }
  switch (paneName) {
  case 'right':
  case 'Pin Right':
    paneName = '.ag-pinned-right-header';
    break;
  case 'left':
  case 'Pin Left':
    paneName = '.ag-pinned-left-header';
    break;
  default:
    paneName = '.ag-header-viewport';
    break;
  }
  cy.get('body').then(() => {
    cy.wrap(horizontalScrolls).each((horizontalScroll) => {
      if (tableNum.toString().includes('modal')) {
        cy.get('.modal-body').find(paneName).then((currentHeaders) => {
          scrollIfHeaderNotFound(currentHeaders, searchTerm, valueType, tableNum, horizontalScroll);
        });
      } else {
        cy.get('body').find(paneName).eq(tableNum)
          .then((currentHeaders) => {
            scrollIfHeaderNotFound(currentHeaders, searchTerm, valueType, tableNum, horizontalScroll);
          });
      }
    });
  })
    .then(() => {
      if (valueType.includes('name')) {
        cy.get(`.ag-header-cell:contains("${searchTerm}")`).should('be.visible');
      } else {
        cy.get(`[col-id=${searchTerm}]`).should('be.visible');
      }
      // eslint-disable-next-line cypress/no-unnecessary-waiting
      cy.wait(500);
    });
}

export function scrollIfHeaderNotFound(currentHeaders, searchTerm, valueType, tableNum, horizontalScroll) {
  let found = false;
  cy.get('body').then(() => {
    cy.wrap(currentHeaders).find('.ag-header-cell').each((currentHeaderCell) => {
      if (valueType.includes('name')) {
        if (currentHeaderCell.text().trim().includes(searchTerm)) {
          found = true;
        }
      } else if (currentHeaderCell.attr('col-id') === searchTerm) {
        found = true;
      }
    });
  })
    .then(() => {
      if (!found) {
        scrollHorizontally(horizontalScroll, tableNum);
      }
    });
}

export function scrollHorizontally(scrollType, tableNum) {
  cy.get('body').then(() => {
    switch (tableNum) {
    case 'modal':
    case '.modal':
      cy.get('.modal-body').find('.ag-body-horizontal-scroll-viewport')
        .last().scrollTo(scrollType, {ensureScrollable: false});
      break;
    case 'header':
      cy.get('.ag-header-viewport').first().scrollTo(scrollType, {ensureScrollable: false});
      break;
    // eslint-disable-next-line no-undefined
    case undefined:
      cy.get('.ag-body-horizontal-scroll-viewport').first().scrollTo(scrollType, {ensureScrollable: false});
      break;
    default:
      cy.get('.ag-body-horizontal-scroll-viewport').eq(tableNum).scrollTo(scrollType, {ensureScrollable: false});
      break;
    }
  }).then(() => {
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(500);
  });
}

export function scrollTableVertically(scrollLocation) {
  switch (scrollLocation) {
  case 'bottom':
  case 'top':
  case 'center':
    cy.get('.ag-body-viewport').scrollTo(scrollLocation, {ensureScrollable: false});
    break;
  default:
    cy.get('.ag-body-viewport').scrollTo(0, `${scrollLocation.toString()}%`, {ensureScrollable: false});
    break;
  }
  // eslint-disable-next-line cypress/no-unnecessary-waiting
  cy.wait(1000);
}

export function tableRowIndexVisible(rowNum) {
  const isInView = Cypress.$('.ag-center-cols-viewport').find(`[row-index=${rowNum}]`).length;
  let rowIndexVisible = false;
  if (isInView > 0) {
    rowIndexVisible = true;
  }
  return rowIndexVisible;
}

export function scrollToRowIndex(rowNum, tableNum) {
  cy.task('log', `Scrolling to row index ${rowNum}`);
  let rowCount = 0;
  let found = false;
  if (typeof tableNum === 'undefined') {
    tableNum = 0;
  }
  cy.get('body').then(() => {
    cy.get('.ag-center-cols-container').find('.ag-row').each((row) => {
      const currentRI = row.attr('row-index');
      if (parseInt(currentRI, 10) === parseInt(rowNum, 10)) {
        found = true;
        return false;
      }
    });
  }).then(() => {
    if (!found) {
      cy.get('body').then(() => {
        scrollTableVertically('bottom');
      }).then(() => {
        cy.get('.ag-center-cols-container .ag-row')
          .then((rows) => {
            rows.sort((a, b) => +a.getAttribute('row-index') - +b.getAttribute('row-index'));
          }).then((sortedRows) => {
            rowCount = sortedRows.last().attr('row-index');
          });
      }).then(() => {
        cy.wrap(getArrayOfScrollsFromValue(rowCount)).each((currentScroll) => {
          cy.get('.ag-center-cols-container').then((currentTable) => {
            const rowFound = currentTable.find(`[row-index=${rowNum}]`).length;
            if (rowFound === 0) {
              scrollTableVertically(currentScroll);
            }
          });
        });
      });
    }
  });
}

export function scrollToChemicalHazardRecursive(chemicalName, currentScroll, level, expanded, getSet) {
  let chemRowIndex = -1;

  if (currentScroll <= 100) {
    cy.get('body').then((currentTable) => {
      if (currentTable.find(`.ag-row:contains("${chemicalName}")`).length > 0) {
        chemRowIndex = currentTable.find(`.ag-row:contains("${chemicalName}")`).attr('row-index');
      } else {
        scrollTableVertically(currentScroll);
      }
    }).then(() => {
      if (chemRowIndex < 0) {
        scrollToChemicalHazardRecursive(chemicalName, currentScroll + 5, level, expanded, getSet);
      } else {
        switch (level) {
        case 'chemical':
          getSetRowIndexExpandState(chemRowIndex, expanded, getSet);
          break;
        default:
          getSetRowIndexExpandState(chemRowIndex, true, 'set');
          scrollToCategoryHazardRecursive(chemicalName, currentScroll + 5, level,
            chemRowIndex, expanded, getSet);
          break;
        }
      }
    });
  }
}

export function scrollToCategoryHazardRecursive(chemicalName, currentScroll, level, chemRI, expanded, getSet) {
  let found = false;
  const autoName = getAutoColumnId();

  if (currentScroll <= 100) {
    cy.get('body').then(() => {
      scrollTableVertically(currentScroll);
    }).then(() => {
      cy.get('.ag-center-cols-container .ag-row').then((rows) => {
        rows.sort((a, b) => +a.getAttribute('row-index') - +b.getAttribute('row-index'));
      }).then((sortedRows) => {
        cy.get('body').then(() => {
          cy.wrap(sortedRows).each((currentRow) => {
            const currentIndex = currentRow.attr('row-index');
            let currentRowName = currentRow.text().replace(/\s\s+/g, ' ').trim();
            if (currentRow.find(`[col-id=${autoName}]`).length > 0) {
              currentRowName = currentRow.find(`[col-id=${autoName}]`).text().replace(/\s\s+/g, ' ').trim();
            }
            let searchTerm = '';
            switch (level) {
            case 'pod':
              searchTerm = 'point';
              break;
            case 'tox':
              searchTerm = 'toxicity';
              break;
            default:
              searchTerm = level;
              break;
            }

            if (parseInt(currentIndex, 10) >= parseInt(chemRI, 10)) {
              if (currentRowName.includes(searchTerm)) {
                getSetRowIndexExpandState(currentIndex, expanded, getSet);
                found = true;
                return false;
              }
            }
          });
        }).then(() => {
          if (!found) {
            scrollToCategoryHazardRecursive(chemicalName, currentScroll + 5, level, chemRI, expanded, getSet);
          }
        });
      });
    });
  }
}

export function scrolltoColumnValue(colId, value) {
  cy.task('log', `Scrolling to ${value} in the ${colId} column`);
  let rowCount = 0;
  cy.get('body').then(() => {
    scrollToColumn(colId);
  }).then(() => {
    cy.get('body').then((pageBody) => {
      if (pageBody.find(`[col-id=${colId}]:contains("${value}")`).length === 0) {
        cy.get('body').then(() => {
          scrollTableVertically('bottom');
        }).then(() => {
          cy.get('.ag-center-cols-container .ag-row')
            .then(rows => rows.sort((a, b) => +a.getAttribute('row-index') - +b.getAttribute('row-index')))
            .then((sortedRows) => {
              rowCount = sortedRows.last().attr('row-index');
            });
        }).then(() => {
          const vscroll = getArrayOfScrollsFromValue(rowCount);
          cy.wrap(vscroll).each((currentScroll) => {
            cy.get('body').then((pageBody2) => {
              const foundValue = pageBody2.find(`[col-id=${colId}]:contains("${value}")`).length;
              if (foundValue === 0) {
                scrollTableVertically(currentScroll);
              }
            });
          });
        });
      }
    });
  }).then(() => {
    cy.get('body').find(`[col-id=${colId}]:contains("${value}")`).should('exist');
  });
}

export function scrolltoColumnSubValue(colId, value, subvalue) {
  cy.task('log', `Scrolling to ${value}/${subvalue} in the ${colId} column`);
  let totalRows = 0;
  let chemRI = -1;
  cy.get('body').then(() => {
    scrollTableVertically('bottom');
    scrollToColumn(colId, 'id');
  }).then(() => {
    cy.get('.ag-center-cols-clipper').find('.ag-row').last().then((currentRow) => {
      const currRowIndex = currentRow.attr('row-index');
      totalRows = currRowIndex;
    });
  }).then(() => {
    const vscroll = getArrayOfScrollsFromValue(totalRows);
    let found = false;
    cy.wrap(vscroll).each((currentScroll) => {
      if (!found) {
        cy.get('.ag-center-cols-clipper').then((currentTable) => {
          if (currentTable.find(`[col-id=${colId}]:contains("${value}")`).length > 0) {
            chemRI = currentTable.find(`[col-id=${colId}]:contains("${value}")`).attr('row-index');
            if (subvalue === 'chemical') {
              found = true;
            } else {
              cy.get('.ag-center-cols-container .ag-row').then((rows) => {
                rows.sort((a, b) => +a.getAttribute('row-index') - +b.getAttribute('row-index'));
              }).then((sortedRows) => {
                cy.wrap(sortedRows).each((currentRow) => {
                  const currentRI = currentRow.attr('row-index');
                  const currentText = currentRow.find(`[col-id=${colId}]`).text().replace(/\s\s+/g, ' ').trim();

                  if (currentRI > chemRI) {
                    if (currentText.includes(subvalue)) {
                      found = true;
                      return false;
                    }
                    if ((currentText.length > 0) &&
                    (!currentText.includes('toxicity')) &&
                    (!currentText.includes('point'))) {
                      found = true;
                      return false;
                    }
                  }
                });
              }).then(() => {
                if (!found) {
                  scrollTableVertically(currentScroll);
                } else {
                  found = true;
                }
              });
            }
          }
        }).then(() => {
          if (!found) {
            scrollTableVertically(currentScroll);
          } else {
            found = true;
          }
        });
      }
    });
  });
}

export function verifyPopupListItems(expectedItems) {
  const actualColumns = [];
  cy.get('body').then(() => {
    cy.wrap(verticalScrolls).each((currentScroll) => {
      cy.get('body').then(() => {
        scrollPopupList(currentScroll);
      }).then(() => {
        cy.get('.ag-column-select-virtual-list-item').each((currentListItem) => {
          const currentValue = currentListItem.text().replace(/\s\s+/g, ' ').trim();
          if (!actualColumns.includes(currentValue)) { actualColumns.push(currentValue); }
        });
      });
    });
  }).then(() => {
    cy.wrap(actualColumns.sort()).should('deep.equal', expectedItems.sort());
  });
}

export function scrollToPopupListItem(colName) {
  cy.wrap(verticalScrolls).each((currentScroll) => {
    cy.get('.ag-virtual-list-viewport').then((currentPopup) => {
      if (currentPopup.find(`.ag-virtual-list-item:contains("${colName}")`).length === 0) {
        cy.get('.ag-virtual-list-viewport').scrollTo(currentScroll, {ensureScrollable: false});
        // eslint-disable-next-line cypress/no-unnecessary-waiting
        cy.wait(1000);
      }
    });
  });
}

export function scrollPopupList(currentScroll) {
  cy.get('.ag-virtual-list-viewport').scrollTo(currentScroll, {ensureScrollable: false});
  // eslint-disable-next-line cypress/no-unnecessary-waiting
  cy.wait(500);
}

export function showHideColumn(colName, show, tableNum) {
  cy.task('log', `Show ${colName} column: ${show}`);
  showColumnOptionsPopupFirst('columns', tableNum);
  scrollToPopupListItem(colName);
  if (colName.length === 0) {
    cy.get('.ag-virtual-list-item').each((currentListItem) => {
      if (currentListItem.text().replace(/\s\s+/g, ' ').trim().length === 0) {
        if (show) {
          cy.wrap(currentListItem).find('input').check({force: true});
        } else {
          cy.wrap(currentListItem).find('input').uncheck({force: true});
        }
        return false;
      }
    });
  } else {
    cy.contains('.ag-virtual-list-item', colName).find('input').then((currentListItem) => {
      if (show) {
        cy.wrap(currentListItem).check({force: true});
      } else {
        cy.wrap(currentListItem).uncheck({force: true});
      }
    });
  }
  closeColumnOptionsPopup();
}


//* ********************************************Table Row Selection Methods ************************************** */
export function getSetTableSelectAllState(expected, getSet) {
  cy.task('log', `****************************${getSet}ting Table Select All: ${expected}`);
  if (getSet.toLowerCase().includes('get')) {
    switch (expected) {
    case 'checked':
      cy.get('.ag-header-row').find('.ag-checkbox-input-wrapper').should('have.class', 'ag-checked');
      break;
    case 'unchecked':
      cy.get('.ag-header-row').find('.ag-checkbox-input-wrapper').should('not.have.class', 'ag-checked');
      cy.get('.ag-header-row').find('.ag-checkbox-input-wrapper').should('not.have.class', 'ag-indeterminate');
      break;
    case 'indeterminate':
      cy.get('.ag-header-row').find('.ag-checkbox-input-wrapper').should('have.class', 'ag-indeterminate');
      break;
    default:
    }
  } else if (expected) {
    cy.get('.ag-header').find('.ag-checkbox-input-wrapper').find('input').check({force: true});
  } else {
    cy.get('.ag-header').find('.ag-checkbox-input-wrapper').find('input').uncheck({force: true});
  }
}

export function verifyRowSelectionAll(expected) {
  cy.get('.ag-center-cols-clipper').find('.ag-row').each((currentRow) => {
    switch (expected) {
    case 'checked':
      cy.wrap(currentRow).find('.ag-checkbox-input-wrapper')
        .not('.ag-hidden').should('have.class', 'ag-checked');
      break;
    case 'unchecked':
      cy.wrap(currentRow).find('.ag-checkbox-input-wrapper')
        .not('.ag-hidden').should('not.have.class', 'ag-checked');
      cy.wrap(currentRow).find('.ag-checkbox-input-wrapper')
        .not('.ag-hidden').should('not.have.class', 'ag-indeterminate');
      break;
    case 'indeterminate':
      cy.wrap(currentRow).find('.ag-checkbox-input-wrapper')
        .not('.ag-hidden').should('have.class', 'ag-indeterminate');
      break;
    default:
    }
  });
}

export function verifyRowSelectionByChemical(chemicalName, level, expected) {
  cy.task('log', `Verifying selections: ${chemicalName} - ${level} - ${expected}`);
  const autoName = getAutoColumnId();
  let start = 0;
  let stopIndex = 1000;
  let pod = 0;
  let tox = 0;
  scrolltoColumnValue(autoName, chemicalName);
  cy.get('.ag-center-cols-container .ag-row')
    .then((rows) => {
      rows.sort((a, b) => +a.getAttribute('row-index') - +b.getAttribute('row-index'));
    })
    .then((sortedRows) => {
      start = sortedRows.find(`[col-id=${autoName}]:contains('${chemicalName}')`).parent().attr('row-index');
    }).then((sortedRows) => {
      cy.get('body').then(() => {
        cy.wrap(sortedRows).each((currentRow, index) => {
          const currentName = currentRow.find(`[col-id=${autoName}]`).text().replace(/\s\s+/g, ' ').trim();
          cy.task('log', `currentName: ${currentName}`);
          if (currentName.length > 0 &&
            !currentName.toLowerCase().includes('point-of') &&
            !currentName.toLowerCase().includes('toxicity') &&
            index !== start) {
            stopIndex = index;
            return false;
          }
        });
      })
        .then(() => {
          cy.wrap(sortedRows).each((currentRow, index) => {
            const rowText = currentRow.text();

            if (rowText.includes('point') && index < stopIndex && index > start) {
              pod = index;
            }

            if (rowText.includes('toxicity') && index < stopIndex && index > start) {
              tox = index;
            }
          });
        });
    })
    .then(() => {
      cy.task('log', `start: ${start} - stopIndex: ${stopIndex} - pod: ${pod} - tox: ${tox}`);
      let rowNum = 0;
      let range = false;
      switch (level) {
      case 'chemical':
        rowNum = start;
        break;
      case 'pod':
        rowNum = pod;
        break;
      case 'tox':
        rowNum = tox;
        break;
      default:
        range = true;
        break;
      }
      if (range) {
        let startRange = 0;
        let endRange = 0;
        switch (level) {
        case 'chemical all':
          startRange = start;
          endRange = stopIndex;
          break;
        case 'pod all':
          startRange = pod;
          endRange = tox;
          break;
        case 'tox all':
          startRange = tox;
          endRange = stopIndex;
          break;
        default:
        }
        cy.get('.ag-center-cols-clipper').find('.ag-row').each((currentRow) => {
          const currentRI = currentRow.attr('row-index');
          cy.task('log', `${currentRI} row text: ${currentRow.text().replace(/\s\s+/g, ' ').trim()}`);
          cy.wrap(currentRow).find('.ag-checkbox-input-wrapper').not('.ag-hidden')
            .then((currentCheckbox) => {
              const currentClass = currentCheckbox.attr('class');

              if (currentRI >= startRange && currentRI < endRange) {
                let pass = false;
                cy.task('log', `Select - expected: ${expected} - actual: ${currentClass}`);
                switch (expected) {
                case 'checked':
                  pass = currentClass.includes('ag-checked');
                  break;
                case 'unchecked':
                  pass = (!currentClass.includes('ag-checked')) && (!currentClass.includes('ag-indeterminate'));
                  break;
                case 'indeterminate':
                  pass = currentClass.includes('ag-indeterminate');
                  break;
                default:
                }
                cy.wrap(pass).should('be.true');
              }
            });
        });
      } else {
        scrollToRowIndex(rowNum);
        cy.get('.ag-center-cols-clipper').find(`[row-index=${rowNum}]`).find('.ag-checkbox-input-wrapper')
          .not('.ag-hidden')
          .then((currentCheckbox) => {
            const currentClass = currentCheckbox.attr('class');
            let pass = false;
            cy.task('log', `Select - expected: ${expected} - actual: ${currentClass}`);
            switch (expected) {
            case 'checked':
              pass = currentClass.includes('ag-checked');
              break;
            case 'unchecked':
              pass = (!currentClass.includes('ag-checked')) && (!currentClass.includes('ag-indeterminate'));
              break;
            case 'indeterminate':
              pass = currentClass.includes('ag-indeterminate');
              break;
            default:
            }
            cy.wrap(pass).should('be.true');
          });
      }
    });
}

export function getTableRowIndex(colId, searchTerm) {
  scrolltoColumnValue(colId, searchTerm);
  return Cypress.$(`[col-id=${colId}]:contains('${searchTerm}')`).parent().attr('row-index');
}

export function getSetHierarchyRowSelectionState(chemicalName, level, offset, selected, getSet) {
  cy.task('log', `****************************\nSelecting ${chemicalName} - ${level} - ${offset} - ${selected}`);
  const autoName = getAutoColumnId();
  cy.get('body').then(() => {
    scrolltoColumnValue(autoName, chemicalName);
  }).then(() => {
    cy.contains(`[col-id=${autoName}]`, chemicalName).then((currentCell) => {
      const chemRowIndex = currentCell.parent().attr('row-index');
      // cy.task('log', `chem row: ${chemRowIndex}`);
      if (!((offset === 0) && (level.toLowerCase().includes('chemical')))) {
        getSetRowIndexExpandState(chemRowIndex, true, 'set');
      }

      switch (level) {
      case 'chemical':
        getSetRowSelectionStateByRowIndex(parseInt(chemRowIndex, 10) + parseInt(offset, 10), selected, getSet);
        break;
      default:
        getSetHierarchySelectRecursive(autoName, chemicalName, 0, level, offset, chemRowIndex, selected, getSet);
        break;
      }
    });
  });
}

export function getSetHierarchySelectRecursive(autoName, chemicalName, currentScroll, level, offset,
  chemRI, selected, getSet) {
  let found = false;
  cy.task('log', `current scroll: ${currentScroll} - chemRI: ${chemRI} - selected: ${selected}`);
  if (currentScroll <= 100) {
    cy.get('body').then(() => {
      scrollTableVertically(currentScroll);
    }).then(() => {
      cy.get('.ag-center-cols-container .ag-row')
        .then((rows) => {
          rows.sort((a, b) => +a.getAttribute('row-index') - +b.getAttribute('row-index'));
        })
        .then((sortedRows) => {
          cy.wrap(sortedRows).each((currentRow) => {
            const currentRowName = currentRow.find(`[col-id=${autoName}]`).text().replace(/\s\s+/g, ' ').trim();
            const currentIndex = currentRow.attr('row-index');

            if ((parseInt(currentIndex, 10) > parseInt(chemRI, 10)) && (currentRowName.includes(level))) {
              cy.task('log', `found level: ${level}`);

              if (offset > 0) {
                getSetRowIndexExpandState(currentIndex, true, 'set');
              }
              const selectRI = parseInt(currentIndex, 10) + parseInt(offset, 10);
              cy.task('log', `offset: ${offset} - current Index: ${currentIndex} - select index: ${selectRI}`);
              getSetRowSelectionStateByRowIndex(selectRI, selected, getSet);
              found = true;
              return false;
            }

            if (found) {
              return false;
            }
          });
        }).then(() => {
          if (!found) {
            getSetHierarchySelectRecursive(autoName, chemicalName, currentScroll + 5, level,
              offset, chemRI, selected, getSet);
          }
        });
    });
  }
}

export function getSetRowSelectionStateByRowIndex(rowNum, selected, getSet) {
  cy.task('log', `*****************************${getSet}ting Select Row Index ${rowNum}: ${selected}`);
  cy.get('body').then(() => {
    cy.get('.grid-header').scrollIntoView();
  }).then(() => {
    scrollToRowIndex(rowNum);
  }).then(() => {
    cy.get('.ag-center-cols-clipper').find(`[row-index=${rowNum}]`).then((currentRow) => {
      const sel = currentRow.find('.ag-checkbox-input-wrapper').not('.ag-hidden').attr('class');
      const chk = sel.indexOf('ag-checked');
      cy.wrap(currentRow).find('.ag-selection-checkbox').not('.ag-invisible').find('.ag-checkbox-input-wrapper')
        .find('input')
        .then((currentCheckbox) => {
          switch (getSet) {
          case 'set':
            if (selected) {
              cy.wrap(currentCheckbox).check({force: true});
            } else {
              cy.wrap(currentCheckbox).uncheck({force: true});
            }
            break;
          case 'get':
            if (selected) {
              cy.wrap(chk).should('be.gte', 0, 'Checkbox is not checked');
            } else {
              cy.wrap(chk).should('be.lt', 0);
            }
            break;
          default:
          }
        });
    });
  });
}

//* **************************************Column Menu Methods************************************ */
export function showColumnOptionsPopupFirst(menutype, tableNum) {
  cy.task('log', `*********************Opening First Column Options Popup: ${menutype}******************************`);
  if (typeof tableNum === 'undefined') {
    tableNum = 0;
  }

  let tableLoc = 'body';
  let headerName = '';
  let cid = '';
  if (tableNum.toString().includes('modal')) {
    tableLoc = '.modal';
    tableNum = 0;
  }
  cy.get('body')
    .then(() => {
      scrollHorizontally('left', tableNum);
    })
    .then(() => {
      cy.get(tableLoc).then((currentTable) => {
        cy.wrap(horizontalScrolls).each((currentScroll) => {
          const menus = currentTable.find('.ag-header-cell-menu-button').length;
          if (menus > 0) {
            cid = currentTable.find('.ag-header-cell-menu-button').first().parents('.ag-header-cell').attr('col-id');
          } else {
            scrollHorizontally(currentScroll);
          }
        });
      });
    })
    .then(() => {
      if (tableLoc.includes('modal')) {
        showColumnOptionsPopup(cid, menutype, tableLoc);
      } else {
        showColumnOptionsPopup(cid, menutype, tableNum);
      }
    });
}

export function showColumnOptionsPopup(colId, colMenuType, tableNum, paneName) {
  let tableLoc = 'body';
  if (tableNum === 'modal') {
    tableLoc = '.modal';
    tableNum = 0;
  }
  if (typeof tableNum === 'undefined') {
    tableNum = 0;
  }
  if (typeof paneName === 'undefined') {
    paneName = 'center';
  }

  cy.get('body').then(() => {
    closeColumnOptionsPopup();
  }).then(() => {
    scrollToColumn(colId, 'id', tableNum);
  }).then(() => {
    cy.get(tableLoc).find('.ag-header').find(`[col-id=${colId}]`).then((currentHeader) => {
      cy.get('body').then(() => {
        cy.get(tableLoc).find('.ag-header').find(`[col-id=${colId}]`).click({force: true});
      }).then(() => {
        cy.get(tableLoc).find('.ag-header').find(`[col-id=${colId}]`).trigger('mouseenter', {force: true});
      }).then(() => {
        const dtwp = currentHeader.find('.display-text-with-popover').length;
        if (dtwp > 0) {
          cy.get('body').then(() => {
            cy.get(tableLoc).find('.ag-header').find(`[col-id=${colId}]`).find('.ag-icon-menu')
              .trigger('mouseover', {force: true});
          }).then(() => {
            cy.get(tableLoc).find('.ag-header').find(`[col-id=${colId}]`).find('.ag-icon-menu')
              .click({force: true});
          });
        } else {
          cy.get(tableLoc).find('.ag-header').find(`[col-id=${colId}]`)
            .then((currentHeader2) => {
              const menuPresent = currentHeader2.find('.ag-header-cell-menu-button').length;
              if (menuPresent > 0) {
                cy.get('body').then(() => {
                  cy.wrap(currentHeader2).find('.ag-header-cell-menu-button')
                    .trigger('mouseover', {force: true});
                }).then(() => {
                  cy.wrap(currentHeader2).find('.ag-header-cell-menu-button').click({force: true});
                })
                  .then(() => {
                    cy.get('.ag-popup').should('exist');
                  })
                  .then(() => {
                    cy.get('.ag-popup').find('.ag-tabs-header').find('.ag-tab-selected')
                      .then((selected) => {
                        const selTab = selected.attr('aria-label');
                        let tabClass = '';
                        cy.task('log', `Showing popup: ${colMenuType}`);
                        switch (colMenuType) {
                        case 'filter':
                          if (!selTab.includes('filter')) {
                            tabClass = '.ag-icon-filter';
                          }
                          break;
                        case 'columns':
                          if (!selTab.includes('columns')) {
                            tabClass = '.ag-icon-columns';
                          }
                          break;
                        case 'menu':
                          if (!selTab.includes('general')) {
                            tabClass = '.ag-icon-menu';
                          }
                          break;
                        default:
                        }

                        if (selTab.length > 0) {
                          cy.get('.ag-popup').then((popup) => {
                            if (popup.find(tabClass).length > 0) {
                              cy.wrap(popup).find(tabClass).click({force: true});
                            }
                          });
                        }
                      });
                  });
              }
            });
        }
      });
    });
  });
}

export function getSetPopupListItemSelection(colName, getSet, expected) {
  cy.task('log', `**************************${getSet}ting option: ${colName} - ${expected}*********************************`);
  if (colName === 'all') {
    const actualColumns = [];
    cy.get('body').then(() => {
      cy.wrap(verticalScrolls).each((currentScroll) => {
        cy.get('body').then(() => {
          scrollPopupList(currentScroll);
        }).then(() => {
          cy.get('.ag-column-select-virtual-list-item').each((currentListItem) => {
            const currentValue = currentListItem.text().replace(/\s\s+/g, ' ').trim();
            if (!actualColumns.includes(currentValue)) { actualColumns.push(currentValue); }
          });
        });
      });
    }).then(() => {
      cy.wrap(actualColumns).each((currentItem) => {
        getSetPopupListItemSelection(currentItem, getSet, expected);
      });
    });
  } else {
    cy.get('body').then(() => {
      scrollToPopupListItem(colName);
    }).then(() => {
      cy.get('.ag-virtual-list-viewport').find('.ag-virtual-list-item').each((currentListItem) => {
        const currentFilter = currentListItem.text().replace(/\s\s+/g, ' ').trim();
        if (currentFilter === colName) {
          switch (getSet) {
          case 'set':
            if (expected) {
              cy.wrap(currentListItem).find('input').check({force: true});
              cy.wrap(currentListItem).find('.ag-checkbox-input-wrapper').should('have.class', 'ag-checked');
            } else {
              cy.wrap(currentListItem).find('input').uncheck({force: true});
              cy.wrap(currentListItem).find('.ag-checkbox-input-wrapper').should('not.have.class', 'ag-checked');
            }
            break;
          case 'get':
            if (expected) {
              cy.wrap(currentListItem).find('.ag-checkbox-input-wrapper').should('have.class', 'ag-checked');
            } else {
              cy.wrap(currentListItem).find('.ag-checkbox-input-wrapper').should('not.have.class', 'ag-checked');
            }
            break;
          default:
            break;
          }
        }
      });
    });
  }
}

export function getSetPopupListSelectAllState(getSet, selected) {
  cy.get('body').then(() => {
    cy.get('.ag-popup').then((currentPopup) => {
      const selectAll1 = currentPopup.find('.ag-column-select-header-checkbox').find('input').length;
      const selectAll2 = currentPopup.find('.ag-virtual-list-item:contains("Select All")').length;
      if (selectAll1 > 0) {
        if (getSet.includes('set')) {
          cy.get('body').then(() => {
            switch (selected) {
            case 'unchecked':
              cy.get('.ag-popup').find('.ag-column-select-header').find('.ag-column-select-header-checkbox').find('input')
                .uncheck();
              break;
            default:
              cy.get('.ag-popup').find('.ag-column-select-header-checkbox').find('input').check();
              break;
            }
          }).then(() => {
            getSetPopupListSelectAllState('get', selected);
          });
        } else {
          switch (selected) {
          case 'unchecked':
            cy.get('.ag-column-select-header-checkbox').find('.ag-checkbox-input-wrapper').should('not.have.class', 'ag-indeterminate');
            cy.get('.ag-column-select-header-checkbox').find('.ag-checkbox-input-wrapper').should('not.have.class', 'ag-checked');
            break;
          case 'indeterminate':
            cy.get('.ag-column-select-header-checkbox').find('.ag-checkbox-input-wrapper').should('have.class', 'ag-indeterminate');
            break;
          default:
            cy.get('.ag-column-select-header-checkbox').find('.ag-checkbox-input-wrapper').should('have.class', 'ag-checked');
            break;
          }
        }
      } else if (selectAll2 > 0) {
        if (getSet.includes('set')) {
          switch (selected) {
          case 'unchecked':
            cy.get('.ag-popup').find('.ag-virtual-list-item:contains("Select All")').find('input').uncheck();
            break;
          default:
            cy.get('.ag-popup').find('.ag-virtual-list-item:contains("Select All")').find('input').check();
            break;
          }
        } else {
          switch (selected) {
          case 'unchecked':
            cy.get('.ag-popup').find('.ag-virtual-list-item:contains("Select All")').find('.ag-checkbox-input-wrapper').should('not.have.class', 'ag-indeterminate');
            cy.get('.ag-popup').find('.ag-virtual-list-item:contains("Select All")').find('.ag-checkbox-input-wrapper').should('not.have.class', 'ag-checked');
            break;
          case 'indeterminate':
            cy.get('.ag-popup').find('.ag-virtual-list-item:contains("Select All")').find('.ag-checkbox-input-wrapper').should('have.class', 'ag-indeterminate');
            break;
          default:
            cy.get('.ag-popup').find('.ag-virtual-list-item:contains("Select All")').find('.ag-checkbox-input-wrapper').should('have.class', 'ag-checked');
            break;
          }
        }
      }
    });
  });
}

export function testPinColumn(columnId, pinOption, tableNum) {
  getSetPinColumn(columnId, pinOption, 'set', tableNum);
  getSetPinColumn(columnId, pinOption, 'get', tableNum);
}

export function getSetPinColumn(colId, pinOption, getSet, tableNum) {
  cy.task('log', `****************************${getSet}ting Column Pin: ${colId}`);
  if (typeof tableNum === 'undefined') { tableNum = 0; }
  cy.get('body').then(() => {
    showColumnOptionsPopup(colId, 'menu', tableNum);
  }).then(() => {
    cy.get('body').then((bodyObject) => {
      if (bodyObject.find('.ag-menu-list').length > 0) {
        if (getSet.includes('set')) {
          cy.get('body').then(() => {
            showColumnOptionsPopup(colId, 'menu', tableNum);
          }).then(() => {
            cy.get('.ag-menu-list').contains('.ag-menu-option', 'Pin Column').click();
          }).then(() => {
            cy.contains('.ag-menu-option', pinOption).click();
          })
            .then(() => {
              closeColumnOptionsPopup();
            });
        } else {
          closeColumnOptionsPopup();
          let tableLoc = 'body';
          let paneName = '.ag-header-viewport';
          switch (pinOption) {
          case 'Pin Right':
            paneName = '.ag-pinned-right-header';
            break;
          case 'Pin Left':
            paneName = '.ag-pinned-left-header';
            break;
          default:
          }
          scrollToColumn(colId, 'id', tableNum, paneName);

          if (tableNum.toString().includes('modal')) {
            tableNum = 0;
            tableLoc = '.modal';
          }

          cy.get(tableLoc).find(paneName).eq(tableNum).find(`[col-id=${colId}]`)
            .should('exist');
        }
      }
    });
  });
}

export function closeColumnOptionsPopup() {
  cy.get('body').then((table) => {
    const beforePopups = table.find('.ag-popup').length;
    if (beforePopups > 0) {
      cy.get('.ag-popup').then((popup) => {
        if (popup.find('.ag-icon-menu').length > 0) {
          cy.get('body').then(() => {
            cy.wrap(popup).find('.ag-tab-selected').find('.ag-icon').click({force: true});
          }).then(() => {
            const afterPopups = beforePopups - 1;
            if (afterPopups <= 0) {
              cy.get('.ag-popup').should('not.exist');
            } else {
              cy.get('.ag-popup').should('have.length', afterPopups);
            }
          });
        }
      });
    }
  });
}


export function verifyColumnSort(columnId, ascending, sortAsString, tableNum) {
  cy.task('log', '*****************************Verifying Sort *************************************** ');
  if (typeof tableNum === 'undefined') {
    tableNum = 0;
  }
  let actual = [];
  let expected = [];

  cy.get('body').then(() => {
    scrollToColumn(columnId, 'id', tableNum);
  }).then(() => {
    cy.get('.ag-center-cols-container').eq(tableNum).find('.ag-row')
      .then((rows) => {
        rows.sort((a, b) => +a.getAttribute('row-index') - +b.getAttribute('row-index'));
      })
      .then((sortedRows) => {
        cy.wrap(sortedRows).each((sortedRow) => {
          cy.wrap(sortedRow).find(`[col-id=${columnId}]`)
            .then((currentCell) => {
              let currentValue = '';
              const pops = currentCell.find('.popoverLabel').length;
              if (pops > 0) {
                currentValue = currentCell.find('.popoverLabel').text().replace(/\s\s+/g, ' ').trim();
              } else {
                currentValue = currentCell.text().replace(/\s\s+/g, ' ').trim();
              }
              if (!sortAsString) {
                currentValue = currentValue.replace('mg/kg/day', '').replace('%', '').replace('-', '').replace('NA', '')
                  .trim();
              }
              if (currentValue.length > 0) {
                actual.push(currentValue);
              }
            });
        });
      })
      .then(() => {
        let arr = [];
        cy.wrap(actual).then((currentActual) => {
          if (currentActual.length === 0) {
            arr = actual.slice(0, actual.length);

            if (sortAsString) {
              expected = arr.slice().sort();
            } else {
              expected = arr.slice().sort((a, b) => {
                if (parseFloat(a) < parseFloat(b)) {
                  return -1;
                }
                if (parseFloat(a) > parseFloat(b)) {
                  return 1;
                }
                return 0;
              });
            }

            if (!ascending) {
              expected = expected.reverse();
            }
            cy.task('log', `actual section: ${arr}\nexpected:       ${expected}`);
            cy.wrap(arr).should('deep.equal', expected);
          }
        });
      });
  });
}

export function resetColumns(tableNum) {
  if (typeof tableNum === 'undefined') {
    tableNum = 0;
  }
  cy.task('log', `*********************Resetting Columns ${tableNum}******************************`);
  cy.get('body').then(() => {
    showColumnOptionsPopupFirst('menu', tableNum);
  }).then(() => {
    cy.get('.ag-menu-list').contains('.ag-menu-option', 'Reset Columns').click({force: true});
  }).then(() => {
    closeColumnOptionsPopup();
  });
}

export function showHideAllColumns(show, tableNum) {
  cy.task('log', `Show All Columns: ${show}`);
  if (typeof tableNum === 'undefined') {
    tableNum = 0;
  }
  cy.get('body').then(() => {
    scrollHorizontally('left');
  }).then(() => {
    showColumnOptionsPopupFirst('columns', tableNum);
  }).then(() => {
    if (show) {
      cy.get('.ag-column-select-header-checkbox').find('input').check();
    } else {
      cy.get('.ag-column-select-header-checkbox').find('input').uncheck();
    }
  })
    .then(() => {
      closeColumnOptionsPopup();
      cy.task('log', 'All Columns shown\n');
    });
}

export function verifyColumnPresent(colId, show, tableNum) {
  cy.task('log', `Verify column ${colId} is present: ${show}`);
  let tableLoc = 'body';
  if (typeof tableNum === 'undefined') {
    tableNum = 0;
  }
  if (tableNum.toString().includes('modal')) {
    tableLoc = '.modal';
    tableNum = 0;
  }
  cy.get('body').then(() => {
    cy.wrap(horizontalScrolls).each((currentScroll) => {
      cy.get('body').then(() => {
        cy.get(tableLoc).then((currentTable) => {
          if (currentTable.find(`[col-id=${colId}]`).length === 0) {
            scrollHorizontally(currentScroll);
          }
        });
      });
    });
  })
    .then(() => {
      cy.get(tableLoc).then((currentTable) => {
        const columnFound = currentTable.find(`[col-id=${colId}]`).length;
        if (show) {
          cy.wrap(columnFound).should('be.greaterThan', 0);
        } else {
          cy.wrap(columnFound).should('be.lte', 0);
        }
      });
    });
}

export function showColumnOptionsPopupByName(colName, colMenuType) {
  cy.task('log', `Opening Column Popup for ${colName}`);
  cy.scrollTo('topLeft');
  scrollToColumn(colName, 'name');
  cy.contains('.ag-header-cell', colName).then((currentHeader) => {
    const cid = currentHeader.attr('col-id');
    cy.task('log', `cid: ${cid}`);
    showColumnOptionsPopup(cid, colMenuType);
  });
}

//* ******************************************************Column Filter Tests********************************************* */
export function verifyFilterOption(filterOption, expected) {
  cy.wrap(filterOption).find('.ag-checkbox-input-wrapper').then((currentCheckbox) => {
    const currentIcon = currentCheckbox.attr('class');
    let currentState = '';
    if (currentIcon.indexOf('-checked') > 0) {
      currentState = 'checked';
    } else if (currentIcon.indexOf('unchecked') > 0) {
      currentState = 'unchecked';
    } else if (currentIcon.indexOf('indeterminate') > 0) {
      currentState = 'indeterminate';
    } else {
      currentState = 'unchecked';
    }
    expect(currentState).to.eq(expected);
  });
}

export function testFilterMenuSearch(colId) {
  cy.get('body').then(() => {
    showColumnFilterMenu(colId);
  }).then(() => {
    cy.get('body').then((pageBody) => {
      const pops = pageBody.find('.ag-popup').length;
      if (pops > 0) {
        cy.get('.ag-popup').then((columnMenuPopup) => {
          const filterMenu = columnMenuPopup.find('.ag-icon-filter').length;
          if (filterMenu > 0) {
            cy.get('.ag-popup').find('.ag-filter').find('.ag-set-filter-list').find('.ag-set-filter-item')
              .eq(1)
              .then((filterOption) => {
                const searchTerm = filterOption.text().replace(/\s\s+/g, ' ').trim().substring(0, 2)
                  .toLowerCase();
                cy.get('body').then(() => {
                  cy.get('.ag-popup').find('.ag-filter').find('.ag-mini-filter').find('input')
                    .click();
                }).then(() => {
                  cy.get('.ag-popup').find('.ag-filter').find('.ag-mini-filter').find('input')
                    .clear();
                }).then(() => {
                  cy.get('.ag-popup').find('.ag-filter').find('.ag-mini-filter').find('input')
                    .type(searchTerm);
                })
                  .then(() => {
                    cy.get('.ag-popup').find('.ag-filter').find('.ag-set-filter-list').find('.ag-set-filter-item')
                      .each((currentFilterOption) => {
                        const currentVal = currentFilterOption.text().toLowerCase();
                        if (!currentVal.includes('select all')) {
                          expect(currentVal.indexOf(searchTerm.toLowerCase()), `current: ${currentVal} - expected:${searchTerm.toLowerCase()}`).to.be.gte(0);
                        }
                      });
                  })
                  .then(() => {
                    cy.get('.ag-popup').find('.ag-filter').find('.ag-mini-filter').find('input')
                      .click();
                  })
                  .then(() => {
                    cy.get('.ag-popup').find('.ag-filter').find('.ag-mini-filter').find('input')

                      .clear();
                  });
              });
            closeColumnOptionsPopup();
          }
        });
      }
    });
  });
}

export function clearColumnFilterTextFieldById(colId, tableNum) {
  cy.task('log', `Clearing Filter Text Field: ${colId}`);
  if (typeof tableNum === 'undefined') { tableNum = 0; }
  cy.get('body').then(() => {
    scrollToColumn(colId, 'id', tableNum);
  }).then(() => {
    cy.get('.ag-body-viewport').eq(tableNum).find(`[col-id=${colId}]`).first()
      .then((currentColumn) => {
        const colNum = currentColumn.attr('aria-colindex');
        cy.get('.ag-header-viewport').eq(tableNum).find(`[aria-colindex="${colNum}"]`).last()
          .then((currentFilter) => {
            const ff = currentFilter.find('.ag-text-field').not('.ag-disabled').length;
            if (ff > 0) {
              scrollToColumn(colId, 'id', tableNum);
              cy.wrap(currentFilter).find('input').clear();
              cy.task('log', `${colId} text field is cleared`);
            }
          });
      });
  });
}

export function testColumnFilterTextFieldsById(colId, filterOption, searchTerm, tableNum) {
  if (typeof tableNum === 'undefined') { tableNum = 0; }
  cy.get('body').then(() => {
    filterColumnTextFieldById(colId, searchTerm, tableNum);
  }).then(() => {
    verifyFilterRowsById(colId, filterOption, searchTerm, tableNum);
  })
    .then(() => {
      clearColumnFilterTextFieldById(colId, tableNum);
    });
}


export function filterColumnTextFieldById(colId, searchTerm, tableNum) {
  cy.task('log', `Filtering ${colId} column text field : ${searchTerm}`);
  if (typeof tableNum === 'undefined') { tableNum = 0; }
  cy.get('body').then(() => {
    scrollToColumn(colId, 'id', tableNum);
  }).then(() => {
    cy.get('.ag-body-viewport').eq(tableNum).find(`[col-id=${colId}]`).first()
      .then((currentColumn) => {
        const colNum = currentColumn.attr('aria-colindex');
        if (typeof searchTerm !== 'undefined') {
          cy.get('body').then(() => {
            cy.get('.ag-header-viewport').eq(tableNum).find(`[aria-colindex=${colNum}]`).find('.ag-input-field')
              .not('.ag-disabled')
              .not('.ag-hidden')
              .click({force: true});
          }).then(() => {
            cy.get('.ag-header-viewport').eq(tableNum).find(`[aria-colindex=${colNum}]`).find('.ag-input-field')
              .not('.ag-disabled')
              .not('.ag-hidden')
              .clear();
          }).then(() => {
            cy.get('.ag-header-viewport').eq(tableNum).find(`[aria-colindex=${colNum}]`).find('.ag-input-field')
              .not('.ag-disabled')
              .not('.ag-hidden')
              .type(searchTerm);
          });
        }
      });
  }).then(() => {
    cy.task('log', 'Filter text field is set');
  });
}


export function testFilterSelectAll(colId, selected) {
  cy.get('body').then(() => {
    showColumnFilterMenu(colId);
  }).then(() => {
    cy.get('body').then((pageBody) => {
      const pops = pageBody.find('.ag-popup').length;
      if (pops > 0) {
        cy.get('.ag-popup').then((columnMenuPopup) => {
          const filterMenu = columnMenuPopup.find('.ag-icon-filter').length;
          if (filterMenu > 0) {
            let checked = 'checked';
            cy.get('body').then(() => {
              cy.wrap(columnMenuPopup).find('.ag-icon-filter').click();
            })
              .then(() => {
                if (!selected) { checked = 'unchecked'; }
                setFilterSelectAll(colId, selected);
                verifyFilterSelectAllState(colId, checked);
              })
              .then(() => {
                showColumnFilterMenu(colId);
                cy.get('.ag-popup').find('.ag-filter').find('.ag-set-filter-list').find('.ag-set-filter-item')
                  .each((filterOption) => {
                    verifyFilterOption(filterOption, checked);
                  });
              });
          }
        });
      }
    });
  });
}


export function clearAllColumnFiltersByPopup(tableNum) {
  cy.task('log', 'Clearing All Column Filters');
  if (typeof tableNum === 'undefined') { tableNum = 0; }
  let colids = [];
  cy.get('body').then(() => {
    cy.wrap(horizontalScrolls).each((currentScroll) => {
      cy.get('body').then(() => {
        scrollHorizontally(currentScroll, tableNum);
      }).then(() => {
        cy.get('.ag-header-viewport').eq(tableNum).find('.ag-header-row').not('.ag-header-row-column-group')
          .first()
          .find('.ag-header-cell')
          .each((currentHeaderCell) => {
            const currentColumnId = currentHeaderCell.attr('col-id');
            if (typeof currentColumnId !== 'undefined' && (currentColumnId.length > 0 && !colids.includes(currentColumnId))) {
              colids.push(currentColumnId);
            }
          });
      });
    });
  })
    .then(() => {
      cy.wrap(colids).each((currentColumnId) => {
        cy.get('body').then(() => {
          showColumnFilterMenu(currentColumnId, tableNum);
        }).then(() => {
          cy.get('body').then((pageBody) => {
            const agpop = pageBody.find('.ag-popup').length;
            if (agpop > 0) {
              cy.get('.ag-popup').then((popup) => {
                const ffs = popup.find('.ag-text-field-input').length;
                const cbs = popup.find('.ag-filter-virtual-list-item').length;
                if (ffs > 0) { cy.get('.ag-popup').find('.ag-text-field-input').first().clear(); }
                if (cbs > 0) { cy.get('.ag-popup').contains('.ag-filter-virtual-list-item', 'Select All').find('input').check(); }
              });
            }
          }).then(() => {
            closeColumnOptionsPopup();
          });
        });
      });
    });
}


export function clearColumnFilterPopup(colId, tableNum) {
  if (typeof tableNum === 'undefined') { tableNum = 0; }
  cy.get('body').then(() => {
    showColumnFilterMenu(colId, tableNum);
  }).then(() => {
    cy.get('body').then((pageBody) => {
      const pops = pageBody.find('.ag-popup').length;
      if (pops > 0) {
        cy.get('.ag-popup').then((popup) => {
          const filterchecks = popup.find('.ag-set-filter-item').length;
          if (filterchecks > 0) {
            getSetPopupListItemSelection('(Select All)', 'set', false);
            getSetPopupListItemSelection('(Select All)', 'set', true);
          } else {
            filterByColumnPopup('Equals', '', 0);
          }
        });
      }
    }).then(() => {
      closeColumnOptionsPopup();
    });
  });
}


export function showColumnFilterMenu(colId, tableNum) {
  if (typeof tableNum === 'undefined') { tableNum = 0; }
  cy.get('body').then(() => {
    closeColumnOptionsPopup();
  }).then(() => {
    scrollToColumn(colId, 'id', tableNum);
  }).then(() => {
    cy.get('.ag-header-viewport').eq(tableNum).find(`[col-id=${colId}]`).then((currentHeader) => {
      const filterIcon = currentHeader.find('.ag-floating-filter-button').not('.ag-hidden').length;
      const filterIconButton = currentHeader.find('.ag-floating-filter-button-button').not('.ag-hidden').length;
      const menuFound = currentHeader.find('.ag-header-cell-menu-button').not('.ag-hidden').length;
      if ((filterIcon > 0) && (filterIconButton > 0)) {
        cy.wrap(currentHeader).find('.ag-floating-filter-button-button').not('.ag-hidden').click({force: true});
      } else if (menuFound > 0) {
        showColumnOptionsPopup(colId, 'menu', tableNum);
      }
    })
      .then(() => {
        cy.get('body').then((pageBody) => {
          if (pageBody.find('.ag-popup').length > 0) {
            cy.get('.ag-popup').then((columnMenuPopup) => {
              cy.wrap(columnMenuPopup).find('.ag-tab-selected').then((currentTab) => {
                if (!currentTab.attr('aria-label').includes('filter')) {
                  if (columnMenuPopup.find('.ag-icon-filter').length > 0) {
                    cy.wrap(columnMenuPopup).find('.ag-icon-filter').click({force: true});
                  } else {
                    closeColumnOptionsPopup();
                  }
                }
              });
            });
          }
        });
      });
  });
}


export function isFilterOptionCorrect(filterOption, expected) {
  cy.wrap(filterOption).find('.ag-checkbox-input-wrapper').then((currentCheckbox) => {
    const currentIcon = currentCheckbox.attr('class');

    let currentState = '';
    if (currentIcon.indexOf('-checked') > 0) { currentState = 'checked'; } else if (currentIcon.indexOf('unchecked') > 0) { currentState = 'unchecked'; } else if (currentIcon.indexOf('indeterminate') > 0) { currentState = 'indeterminate'; } else { currentState = 'unchecked'; }
    expect(currentState).to.eq(expected);
  });
}

export function columnFilterable(colId, expected, tableNum) {
  if (typeof tableNum === 'undefined') { tableNum = 0; }
  let colNum = 0;
  let filterIcon = 0;
  let filterMenu = 0;
  cy.get('body').then(() => {
    scrollToColumn(colId, 'id', tableNum);
  }).then(() => {
    cy.get('.ag-header-viewport').eq(tableNum).find(`[col-id=${colId}]`).then((currentCell) => {
      colNum = currentCell.attr('aria-colindex');
      if (typeof colNum === 'undefined') {
        colNum = currentCell.find('.ag-header-cell-text').attr('aria-colIndex');
      }
    });
  }).then(() => {
    cy.get('.ag-header-viewport').eq(tableNum).find(`[aria-colindex=${colNum}]`)
      .then((currentColumn) => {
        filterIcon = currentColumn.find('.ag-floating-filter-button-button:visible').not('.ag-hidden').length;
      });
  })
    .then(() => {
      showColumnFilterMenu(colId, tableNum);
    })
    .then(() => {
      cy.get('body').then((pageBody) => {
        filterMenu = pageBody.find('[aria-label=filter]').length;
      });
    })
    .then(() => {
      closeColumnOptionsPopup();
    })
    .then(() => {
      const filterable = parseInt(filterIcon, 10) + parseInt(filterMenu, 10);
      if (expected) {
        cy.wrap(filterable).should('be.gt', 0);
      } else {
        cy.wrap(filterable).should('eq', 0);
      }
    });
}


export function columnFilterableOld(colId, tableNum) {
  if (typeof tableNum === 'undefined') { tableNum = 0; }
  scrollToColumn(colId, 'id', tableNum);
  let colNum = Cypress.$('.ag-header-viewport').eq(tableNum).find(`[col-id=${colId}]`).attr('aria-colindex');
  if (typeof colNum === 'undefined') {
    colNum = Cypress.$('.ag-header-viewport').eq(tableNum).find(`[col-id=${colId}]`).find('.ag-header-cell-text')
      .attr('aria-colIndex');
  }
  const filterIcon = Cypress.$('.ag-header-viewport').eq(tableNum).find(`[aria-colindex=${colNum}]`).find('.ag-floating-filter-button-button:visible')
    .not('.ag-hidden').length;
  showColumnFilterMenu(colId, tableNum);
  const filterMenu = filterPopupPresent();
  closeColumnOptionsPopup();

  if ((filterIcon > 0) || filterMenu) { return true; }
  return false;
}

export function filterPopupPresent() {
  const filterMenu = Cypress.$('[aria-label=filter]').length;
  if (filterMenu > 0) { return true; }
  return false;
}

export function isColumnFilterPopupCorrect(expected) {
  if (expected) {
    cy.get('body').find('.ag-popup').find('.ag-icon-filter').should('exist');
  } else {
    cy.get('body').then((pageBody) => {
      const filterIcon = pageBody.find('.ag-popup').find('.ag-tab').find('.ag-icon-filter').not('.ag-hidden').length;
      cy.wrap(filterIcon).should('eq', 0);
    });
  }
}

export function isColumnFilterPopupCorrectOld(expected) {
  if (expected) {
    cy.get('body').find('.ag-popup').find('.ag-icon-filter').should('exist');
  } else {
    cy.get('body').then((pageBody) => {
      const popup = pageBody.find('.app-container').find('.ag-popup').length;
      cy.task('log', `pop: ${popup}`);
      if (popup > 0) {
        cy.wrap(pageBody).find('.ag-popup').find('.ag-icon-filter')
          .not('.ag-hidden')
          .should('not.exist');
      } else {
        cy.wrap(pageBody).find('.ag-popup').should('not.exist');
      }
    });
  }
}


export function setFilterSelectAll(colId, expected) {
  cy.task('log', `*****************************************Setting Filter Select All: ${expected}*************************************`);
  showColumnFilterMenu(colId);
  cy.get('.ag-popup').find('.ag-filter').find('.ag-set-filter').contains('.ag-set-filter-item', 'Select All')
    .find('.ag-checkbox-input-wrapper')
    .then((selectAllOption) => {
      if (expected) { cy.wrap(selectAllOption).find('input').check(); } else { cy.wrap(selectAllOption).find('input').uncheck(); }
    });
  closeColumnOptionsPopup();
}

export function verifyFilterSelectAllState(colId, expected) {
  cy.task('log', `Check Filter Select All: ${expected}`);
  cy.get('body').then(() => {
    showColumnFilterMenu(colId);
  }).then(() => {
    cy.get('.ag-popup').find('.ag-filter').find('.ag-set-filter-list').contains('.ag-set-filter-item', 'Select All')
      .find('.ag-checkbox-input-wrapper')
      .then((currentCheckbox) => {
        switch (expected) {
        case 'checked':
          cy.wrap(currentCheckbox).should('have.class', 'ag-checked');
          break;
        case 'indeterminate':
          cy.wrap(currentCheckbox).should('have.class', 'ag-indeterminate');
          break;
        case 'unchecked':
          cy.wrap(currentCheckbox).should('not.have.class', 'ag-checked').should('not.have.class', 'ag-indeterminate');
          break;
        default:
        }
      });
  }).then(() => {
    closeColumnOptionsPopup();
  });
}


export function testColumnFilterPopupByName(colName, filterOption, searchTerm, filterBlock, searchTerm2) {
  closeColumnOptionsPopup();
  showColumnOptionsPopupByName(colName, 'filter');
  filterByColumnPopup(filterOption, searchTerm, filterBlock, searchTerm2);
  closeColumnOptionsPopup();
  verifyColumnTextFieldByName(colName, searchTerm, searchTerm2);
  verifyColumnTextFieldEnabledByName(colName, true);
  verifyFilterRows(colName, filterOption, searchTerm, 'none', 'none', 'none', searchTerm2);
  openColumnFilterPopupByName(colName);
  clearFilterByColumnPopup(0);
  closeColumnOptionsPopup();
}

export function testColumnFilterPopupByNameNoTextField(colName, filterOption, searchTerm, toTerm, condition,
  filterOption2, searchTerm2, toTerm2) {
  closeColumnOptionsPopup();
  showColumnOptionsPopupByName(colName, 'filter');
  filterByColumnPopup(filterOption, searchTerm, 0, toTerm);
  setFilterCondition(condition);
  filterByColumnPopup(filterOption2, searchTerm2, 1, toTerm2);
  closeColumnOptionsPopup();
  verifyFilterRows(colName, filterOption, searchTerm, condition, filterOption2, searchTerm2, toTerm, toTerm2);
  showColumnOptionsPopupByName(colName, 'filter');
  clearFilterByColumnPopup(0);
  closeColumnOptionsPopup();
}


export function testColumnFilterPopupByIdNoTextField(colId, filterOption, searchTerm, toTerm, condition,
  filterOption2, searchTerm2, toTerm2) {
  showColumnOptionsPopup(colId, 'filter');
  filterByColumnPopup(filterOption, searchTerm, 0, toTerm);
  setFilterCondition(condition);
  filterByColumnPopup(filterOption2, searchTerm2, 1, toTerm2);
  closeColumnOptionsPopup();
  verifyFilterRowsById(colId, filterOption, searchTerm, condition, filterOption2, searchTerm2, toTerm, toTerm2);
  showColumnOptionsPopup(colId, 'filter');
  clearFilterByColumnPopup(0);
  closeColumnOptionsPopup();
}


export function clearFilterByColumnPopup(filterBlock) {
  cy.task('log', 'Clearing filter');
  cy.get('body').then(() => {
    cy.get('.ag-popup').eq(0).find('.ag-input-field-input').eq(filterBlock)
      .click();
  }).then(() => {
    cy.get('.ag-popup').eq(0).find('.ag-input-field-input').eq(filterBlock)
      .clear();
  });
}

export function clearUserFilterByColumnPopup(filterBlock) {
  cy.task('log', 'Clearing filter');
  cy.get('body').then(() => {
    cy.get('.ag-popup').eq(0).find('.ag-input-field-input').eq(filterBlock)
      .click();
  }).then(() => {
    cy.get('.ag-popup').eq(0).find('.ag-input-field-input').eq(filterBlock)
      .clear();
  });
}


export function verifyFilterRows(colName, filterOption1, filterText1, condition,
  filterOption2, filterText2, filterTo1, filterTo2) {
  cy.task('log', 'Verifying filter rows');
  const cid = getColumnIdByName(colName);
  scrollToColumn(colName, 'name');
  let finalmatch = false;
  cy.get('.ag-center-cols-clipper').find('.ag-row').find(`[col-id=${cid}]`).each((columnValue) => {
    const currentVal = columnValue.text().replace(/\s\s+/g, ' ').trim();
    let match1 = false;
    let match2 = false;
    match1 = verifyFilter(currentVal, filterOption1, filterText1, filterTo1);
    if (condition.includes('AND') || condition.includes('OR')) {
      match2 = verifyFilter(currentVal, filterOption2, filterText2, filterTo2);
    } else {
      match2 = true;
      if (match1) {
        finalmatch = true;
      }
    }
    if ((condition.includes('AND') && match1 && match2) || (condition.includes('OR') && (match1 || match2))) {
      finalmatch = true;
    }
    cy.task('log', `Filter is incorrect: ${filterOption1} - ${filterText1} - ${match1} - ${condition} - ${filterOption2} - ${filterText2} - ${match2}`);
    cy.wrap(finalmatch).should('be.true');
  });
}


export function verifyFilterRowsById(cid, filterOption1, filterText1, condition,
  filterOption2, filterText2, filterTo1, filterTo2) {
  cy.task('log', 'Verifying filter rows by Id');
  scrollToColumn(cid, 'id');
  let finalmatch = false;
  cy.get('.ag-center-cols-clipper').find('.ag-row').find(`[col-id=${cid}]`).each((columnValue) => {
    const currentVal = columnValue.text().replace(/\s\s+/g, ' ').trim();
    let match1 = false;
    let match2 = false;
    match1 = verifyFilter(currentVal, filterOption1, filterText1, filterTo1);
    if (condition.includes('AND') || condition.includes('OR')) {
      match2 = verifyFilter(currentVal, filterOption2, filterText2, filterTo2);
    } else {
      match2 = true;
      if (match1) {
        finalmatch = true;
      }
    }
    if ((condition.includes('AND') && match1 && match2) || (condition.includes('OR') && (match1 || match2))) {
      finalmatch = true;
    }
    cy.task('log', `Filter is incorrect: ${filterOption1} - ${filterText1} - ${match1} - ${condition} - ${filterOption2} - ${filterText2} - ${match2}`);
    cy.wrap(finalmatch).should('be.true');
  });
}


export function verifyUserFilterRows(colId, filterOption1, filterText1, condition,
  filterOption2, filterText2, filterTo1, filterTo2) {
  cy.task('log', 'Verifying filter rows');
  let finalmatch = false;
  cy.get('.ag-center-cols-clipper').find('.ag-row').find(`[col-id=${colId}]`).each((columnValue) => {
    const currentVal = columnValue.text().replace(/\s\s+/g, ' ').trim();
    let match1 = false;
    let match2 = false;
    if (filterOption1.includes('In range')) {
      match1 = verifyFilter(currentVal, filterOption1, filterText1, filterTo1);
    } else {
      match1 = verifyFilter(currentVal, filterOption1, filterText1);
    }
    if (condition.includes('AND') || condition.includes('OR')) {
      if (filterOption2.includes('In range')) {
        match2 = verifyFilter(currentVal, filterOption2, filterText2, filterTo2);
      } else {
        match2 = verifyFilter(currentVal, filterOption2, filterText2);
      }
    } else {
      match2 = true;
      if (match1) {
        finalmatch = true;
      }
    }

    if ((condition.includes('AND') && match1 && match2) || (condition.includes('OR') && (match1 || match2))) {
      finalmatch = true;
    }
    cy.task('log', `Filter is incorrect: ${filterOption1} - ${filterText1} - ${match1} - ${condition} - ${filterOption2} - ${filterText2} - ${match2}`);
    cy.wrap(finalmatch).should('be.true');
  });
}


export function verifyFilter(testText, filterOption, filterText, filterText2) {
  testText = testText.replace(/\s\s+/g, ' ').trim().toLowerCase();
  filterText = filterText.replace(/\s\s+/g, ' ').trim().toLowerCase().trim();
  const filterTextNumber = parseInt(filterText, 10);
  let currentnum = -1;
  let matches = false;
  switch (filterOption) {
  case 'Contains':
    if (testText.includes(filterText)) { matches = true; }
    break;
  case 'Not contains':
    if (testText.includes(filterText)) { matches = true; }
    break;
  case 'Equals':
    if (testText.indexOf(filterText) === 0) { matches = true; }
    break;
  case 'Not equal':
    if (testText.indexOf(filterText) !== 0) { matches = true; }
    break;
  case 'Starts with':
    if (testText.indexOf(filterText) === 0) { matches = true; }
    break;
  case 'Ends with':
    if (testText.indexOf(filterText) === (testText.length - filterText.length)) { matches = true; }
    break;
  case 'Less than':
    currentnum = parseInt(testText.trim(), 10);
    if (currentnum < filterTextNumber) { matches = true; }
    break;
  case 'Less than or equals':
    currentnum = parseInt(testText.trim(), 10);
    if (currentnum <= filterTextNumber) { matches = true; }
    break;
  case 'Greater than':
    currentnum = parseInt(testText.trim(), 10);
    if (currentnum > filterTextNumber) { matches = true; }
    break;
  case 'Greater than or equals':
    currentnum = parseInt(testText.trim(), 10);
    if (currentnum >= filterTextNumber) { matches = true; }
    break;
  case 'In range':
    currentnum = parseInt(testText.trim(), 10);
    filterText2 = filterText2.replace(/\s\s+/g, ' ').trim().toLowerCase();
    if (currentnum >= filterTextNumber && currentnum <= parseInt(filterText2, 10)) { matches = true; }
    break;
  default:
  }
  return matches;
}

export function openColumnFilterPopupByName(colName) {
  for (let x = 0; x < 3; x += 1) {
    openFilterPopup(colName);
    if (isMenuPopupOpen()) {
      break;
    }
  }
}


export function openFilterPopup(colName) {
  cy.task('log', `Opening column filter popup: ${colName}`);
  const hrNum = findHeaderRow('filter');
  const cn = findHeaderRow('name');
  cy.get('body').then((pageBody) => {
    const filterIcon = pageBody.find('.ag-popup').find('.ag-icon-filter').length;
    if (filterIcon === 0) {
      cy.get('.ag-header-viewport').find('.ag-header-row').eq(cn).find('.ag-header-cell')
        .each((currentHeaderCell, index) => {
          const columnText = currentHeaderCell.text().replace(/\s\s+/g, ' ').trim();
          if (columnText.includes(colName)) {
            cy.get('.ag-header-viewport').find('.ag-header-row').eq(hrNum).find('.ag-header-cell')
              .eq(index)
              .then((currentColumnCell) => {
                const filterPopupIcon = currentColumnCell.find('.ag-floating-filter-button').not('.ag-hidden').length;
                if (filterPopupIcon > 0) {
                  cy.wrap(currentColumnCell).find('.ag-floating-filter-button').click();
                } else {
                  cy.task('log', 'no popup icon');
                  showColumnOptionsPopup(colName, 'filter');
                }
              });
          }
        });
    }
  });
}


export function clearColumnFilterTextField(colName) {
  const cNum = findHeaderRow('name');
  const hrNum = findHeaderRow('filter');
  scrollToColumn(colName, 'name');
  cy.task('log', `Clearing Filter Text Field: ${colName}`);
  cy.get('.ag-header-viewport').find('.ag-header-row').eq(cNum).find('.ag-header-cell')
    .each((currentCell, cindex) => {
      const currentColName = currentCell.text().replace(/\s\s+/g, ' ').trim();

      if (currentColName.includes(colName)) {
        cy.get('body').then(() => {
          cy.get('.ag-header-viewport').find('.ag-header-row').eq(hrNum).find('.ag-header-cell')
            .eq(cindex)
            .find('.ag-text-field-input')
            .click();
        }).then(() => {
          cy.get('.ag-header-viewport').find('.ag-header-row').eq(hrNum).find('.ag-header-cell')
            .eq(cindex)
            .find('.ag-text-field-input')
            .clear();
        });
      }
    });
  cy.task('log', 'Filter text field is cleared');
}


export function clearAllColumnFilterTextFields() {
  const hrNum = findHeaderRow('filter');
  cy.task('log', 'Clearing All Filter Text Fields');
  const scrolls = ['left', 'right'];
  cy.wrap(horizontalScrolls).each((horizontalScroll) => {
    cy.get('.ag-header-viewport').then(() => {
      scrollHorizontally(horizontalScroll);
    }).then(() => {
      cy.get('.ag-header-viewport').find('.ag-header-row').eq(hrNum).find('input')
        .each((currentFilter, cindex) => {
          cy.get('.ag-header-viewport').find('.ag-header-row').eq(hrNum).find('input')
            .then((currentFilterInner) => {
              const fields = currentFilterInner.eq(cindex).length;
              if (fields > 0) {
                cy.get('body').then(() => {
                  cy.get('.ag-header-viewport').find('.ag-header-row').eq(hrNum).find('input')
                    .eq(cindex)
                    .click({force: true});
                }).then(() => {
                  cy.get('.ag-header-viewport').find('.ag-header-row').eq(hrNum).find('input')
                    .eq(cindex)
                    .clear({force: true});
                });
              }
            });
        });
    });
  });
}


export function filterByColumnPopup(filterOption, filterText, filterBlock, filterText2) {
  if (typeof filterText !== 'undefined') {
    cy.task('log', `Filtering by column popup: ${filterOption} ${filterText}`);
    cy.get('body').then(() => {
      cy.get('.ag-popup').eq(0).find('.ag-picker-field-icon').eq(filterBlock)
        .click();
    })
      .then(() => {
        cy.contains('.ag-list-item', filterOption).first().click();
      })
      .then(() => {
        if (filterOption.includes('In range')) {
          cy.get('body').then(() => {
            cy.get('.ag-popup').eq(0).find('.ag-filter-body').eq(filterBlock)
              .find('.ag-filter-from')
              .find('.ag-input-field-input')
              .click();
          }).then(() => {
            cy.get('.ag-popup').eq(0).find('.ag-filter-body').eq(filterBlock)
              .find('.ag-filter-from')
              .find('.ag-input-field-input')
              .clear();
          })


            .then(() => {
              if (filterText.length > 0) {
                cy.get('.ag-popup').eq(0).find('.ag-filter-body').eq(filterBlock)
                  .find('.ag-filter-from')
                  .find('.ag-input-field-input')
                  .type(filterText);
              }
            })
            .then(() => {
              cy.get('.ag-popup').eq(0).find('.ag-filter-body').eq(filterBlock)
                .find('.ag-filter-to')
                .find('.ag-input-field-input')
                .click();
            })
            .then(() => {
              cy.get('.ag-popup').eq(0).find('.ag-filter-body').eq(filterBlock)
                .find('.ag-filter-to')
                .find('.ag-input-field-input')
                .clear();
            })
            .then(() => {
              if (typeof filterText2 !== 'undefined') {
                if (filterText2.length > 0) {
                  cy.get('.ag-popup').eq(0).find('.ag-filter-body').eq(filterBlock)
                    .find('.ag-filter-to')
                    .find('.ag-input-field-input')
                    .type(filterText2);
                }
              }
            });
        } else {
          cy.get('body').then(() => {
            cy.get('.ag-popup').eq(0).find('.ag-filter-body').eq(filterBlock)
              .find('.ag-input-field-input')
              .first()
              .click({force: true});
          }).then(() => {
            cy.get('.ag-popup').eq(0).find('.ag-filter-body').eq(filterBlock)
              .find('.ag-input-field-input')
              .first()
              .clear({force: true});
          })
            .then(() => {
              if (typeof filterText !== 'undefined') {
                if (filterText.length > 0) {
                  cy.get('.ag-popup').eq(0).find('.ag-filter-body').eq(filterBlock)
                    .find('input')
                    .first()
                    .type(filterText);
                }
              }
            });
        }
        cy.task('log', `Filter applied: ${filterOption} - ${filterText} - filterBlock: ${filterBlock}`);
      });
  }
}

export function setFilterCondition(condition) {
  if (typeof condition !== 'undefined') {
    cy.task('log', `Setting condition: ${condition}`);
    cy.get('.ag-popup').first().find('.ag-filter-condition').contains('.ag-filter-condition-operator', condition)
      .find('input')
      .check({force: true});
  }
}

export function setUserCondition(condition) {
  cy.get('.ag-popup').first().find('.ag-filter-condition').contains('.ag-filter-condition-operator', condition)
    .find('input')
    .check({force: true});
}

export function filterUsersByColumnPopup(filterOption, filterText, filterBlock, filterText2) {
  cy.task('log', 'Filtering by user filter popup');
  cy.get('body').then(() => {
    cy.get('.ag-popup').find('.ag-picker-field-wrapper').eq(filterBlock).click();
  }).then(() => {
    cy.get('.ag-popup-child').contains('.ag-list-item', filterOption).click();
  }).then(() => {
    if (filterOption.includes('In range')) {
      cy.get('body').then(() => {
        cy.get('.ag-popup').first().find('.ag-text-field-input').eq(filterBlock)
          .find('.ag-filter-from')
          .find('.ag-input-field-input')
          .click();
      }).then(() => {
        cy.get('.ag-popup').first().find('.ag-text-field-input').eq(filterBlock)
          .find('.ag-filter-from')
          .find('.ag-input-field-input')
          .clear();
      }).then(() => {
        if (filterText.length > 0) {
          cy.get('.ag-popup').first().find('.ag-text-field-input').eq(filterBlock)
            .find('.ag-filter-from')
            .find('.ag-input-field-input')
            .type(filterText);
        }
      })
        .then(() => {
          cy.get('.ag-popup').first().find('.ag-text-field-input').eq(filterBlock)
            .find('.ag-filter-to')
            .find('.ag-input-field-input')
            .click();
        })
        .then(() => {
          cy.get('.ag-popup').first().find('.ag-text-field-input').eq(filterBlock)
            .find('.ag-filter-to')
            .find('.ag-input-field-input')
            .clear();
        })
        .then(() => {
          if (filterText.length > 0) {
            cy.get('.ag-popup').first().find('.ag-text-field-input').eq(filterBlock)
              .find('.ag-filter-to')
              .find('.ag-input-field-input')
              .type(filterText2);
          }
        });
    } else {
      cy.get('body').then(() => {
        cy.get('.ag-popup').first().find('.ag-text-field-input').eq(filterBlock)
          .click({force: true});
      }).then(() => {
        cy.get('.ag-popup').first().find('.ag-text-field-input').eq(filterBlock)
          .clear({force: true});
      }).then(() => {
        if (filterText.length > 0) {
          cy.get('.ag-popup').first().find('.ag-text-field-input').eq(filterBlock)
            .type(filterText, {force: true});
        }
      });
    }
  });
  cy.task('log', `Filter applied: ${filterOption} - ${filterText} - filterBlock: ${filterBlock}`);
}


export function verifyFilteredIconByName(colName, expected) {
  cy.task('log', `Checking filtered icon for ${colName} column`);
  const hrNum = findHeaderRow('name');
  if (expected) {
    cy.get('.ag-header-viewport').find('.ag-header-row').eq(hrNum).contains('.ag-header-cell', colName)
      .find('.ag-filter-icon')
      .should('not.have.class', 'ag-hidden');
  } else {
    cy.get('.ag-header-viewport').find('.ag-header-row').eq(hrNum).contains('.ag-header-cell', colName)
      .find('.ag-filter-icon')
      .should('have.class', 'ag-hidden');
  }
}

export function verifyColumnTextFieldEnabledByName(colName, expected) {
  cy.task('log', `Verifying ${colName} column Filter Text Field enabled: ${expected}`);
  const hrNum = findHeaderRow('filter');
  const headerColNum = findHeaderRow('name');
  cy.get('.ag-header-viewport').find('.ag-header-row').eq(headerColNum).find('.ag-header-cell')
    .each((currentColumn) => {
      if (currentColumn.text().includes(colName)) {
        const colindex = currentColumn.attr('aria-colindex');
        cy.get('.ag-header-viewport').find('.ag-header-row').eq(hrNum).find(`[aria-colindex=${colindex}]`)
          .find('.ag-text-field')
          .then((filterTextField) => {
            if (expected) {
              cy.wrap(filterTextField).not('have.class', 'ag-disabled');
            } else {
              cy.wrap(filterTextField).should('have.class', 'ag-disabled');
            }
          });
      }
    });
}


export function closeColumnFilterPopup() {
  cy.get('body').then((table) => {
    const filterPop = table.find('.ag-popup').find('.ag-icon-filter').length;
    if (filterPop > 0) {
      cy.get('.ag-popup').find('.ag-icon-filter').click();
    }
  });
  cy.task('log', 'Filter popup is closed');
}

export function clickUserFilterIcon() {
  cy.get('.ag-header-viewport').find('[col-id=username]').then((currentCell) => {
    const colnum = currentCell.attr('aria-colindex');
    cy.get('.ag-header-viewport').find(`[aria-colindex=${colnum}]`).find('.ag-floating-filter-button-button').click({force: true});
  }).then(() => {
    cy.get('.ag-popup').should('exist');
  });
}

export function closeUserColumnFilterPopup() {
  cy.get('body').then((table) => {
    const filterPop = table.find('.ag-popup').find('.ag-icon-filter').length;
    if (filterPop > 0) {
      clickUserFilterIcon();
    }
  });
  cy.task('log', 'Filter popup is closed');
}


export function searchByColumn(colId) {
  const errors = [];
  cy.get('body').then(() => {
    setFilterSelectAll(colId, false);
  }).then(() => {
    showColumnOptionsPopup(colId, 'filter');
  }).then(() => {
    cy.get('.ag-popup').find('.ag-filter').find('.ag-set-filter-item').each((currentFilterOption) => {
      const selectedItem = currentFilterOption.text().replace(/\s\s+/g, ' ').trim();
      if ((!selectedItem.includes('Select All')) && (!selectedItem.includes('(Blanks)'))) {
        cy.get('body').then(() => {
          cy.get('.ag-popup').find('.ag-filter').contains('.ag-set-filter-item', selectedItem).find('input')
            .check({force: true});
          // eslint-disable-next-line cypress/no-unnecessary-waiting
          cy.wait(500);
        })

          .then(() => {
            cy.get('.ag-center-cols-clipper').find(`[col-id=${colId}]`).each((currentRow) => {
              const currentColValue = currentRow.text().replace(/\s\s+/g, ' ').trim();
              if (currentColValue.length > 0) {
                let expectedTableVal = selectedItem;
                const sci = convertToScienticNotation(selectedItem);
                if ((!selectedItem.includes('Blanks')) && (!selectedItem.includes('-'))) { expectedTableVal = sci; }
                cy.task('log', `col value: ${currentColValue} - expected: ${expectedTableVal}`);
                if (!currentColValue.includes(expectedTableVal)) { errors.push(currentColValue); }
              }
            });
          })
          .then(() => {
            cy.get('.ag-popup').find('.ag-filter').contains('.ag-set-filter-item', selectedItem).find('input')
              .uncheck({force: true});
          });
      }
    });
  })
    .then(() => {
      setFilterSelectAll(colId, true);
    })
    .then(() => {
      cy.task('log', `errors: ${errors}`);
      cy.wrap(errors).should('have.length', 0);
    });
}

export function verifyColumnTextFieldByName(colName, searchTerm, searchTerm2) {
  cy.task('log', `Verifying ${colName} column Filter Text Field: ${searchTerm}`);
  const hrNum = findHeaderRow('filter');
  const headerColNum = findHeaderRow('name');
  let expected = searchTerm;
  scrollToColumn(colName, 'name');
  cy.get('.ag-header-viewport').find('.ag-header-row').eq(headerColNum).contains('.ag-header-cell', colName)
    .then((currentColumn) => {
      const colNum = currentColumn.attr('aria-colindex');
      cy.get('.ag-header-viewport').find('.ag-header-row').eq(hrNum).find(`[aria-colindex="${colNum}"]`)
        .find('.ag-text-field-input')
        .then((currentFilterTextField) => {
          if (typeof searchTerm2 !== 'undefined') {
            expected = `${searchTerm}-${searchTerm2}`;
          }
          cy.wrap(currentFilterTextField).should('have.value', expected);
        });
    });
}

//* ************************************************Table Column Tests************************************************ */
/**
   * Test up to 3 random links
   * @param {*} tableName
   * @param {*} colId
   * @param {*} expectedUrl
   * @param {*} startUrl
   */

export function testClickColumnLinks(colId, expectedUrl, startUrl) {
  cy.get('div.ag-center-cols-container').find('[role=row]').then((tableRows) => {
    scrollToColumn(colId, 'id');
    let numRows = tableRows.length;
    if (numRows > 3) { numRows = 3; }
    const numArr = getArrayOfRandomInts(numRows, 0, numRows - 1);
    cy.wrap(numArr).each((current) => {
      cy.get('div.ag-center-cols-container').find('[role=row]').eq(current).find(`[col-id=${colId}]`)
        .then((currentCell) => {
          if (currentCell.find('a').length > 0) {
            cy.wrap(currentCell).find('a').each((cellLink, index) => {
              const linkSplit = cellLink.text().split(':');
              const newText = linkSplit[0];
              cy.get('body').then(() => {
                cy.get('div.ag-center-cols-container').find('[role=row]').eq(current).find(`[col-id=${colId}]`)
                  .find('a')
                  .eq(index)
                  .invoke('removeAttr', 'target')
                  .click();
              })
                .then(() => {
                  cy.url().should('contain', expectedUrl + newText.replace('/', '%2F').replace(' ', '%20').replace(' ', '%20'));
                })
                .then(() => {
                  cy.visit(startUrl);
                });
            });
          }
        });
    });
  });
}


export function getColumnId(colName) {
  let colId = '';
  for (let x = 0; x < 5; x += 1) {
    cy.scrollTo('topLeft');
    scrollToColumn(colName, 'name');
    colId = Cypress.$(`.ag-header-cell:contains('${colName}')`, {}).attr('col-id');
    cy.task('log', `colid: ${colId}`);
    if (typeof colId !== 'undefined' && colId !== '') {
      break;
    }
  }
  return colId;
}


export function getColumnIdByName(searchTerm) {
  cy.task('log', `Finding Column Id for : ${searchTerm}`);
  const headerRow = findHeaderRow('name');
  let cid = '';
  scrollToColumn(searchTerm, 'name');

  const numCol = Cypress.$('.ag-header-viewport').find('.ag-header-row').eq(headerRow).find('.ag-cell-label-container').length;
  const colId = Cypress.$(`.ag-header-cell:contains('${searchTerm}')`);
  cy.task('log', `colId: ${colId.attr('col-id')}`);
  for (let c = 0; c < numCol; c += 1) {
    const columnText = Cypress.$('.ag-header-viewport').find('.ag-header-row').eq(headerRow).find('.ag-cell-label-container')
      .eq(c)
      .text()
      .replace(/\s\s+/g, ' ')
      .trim();
    cy.task('log', `${c} - current: ${columnText}`);
    if (columnText.includes(searchTerm)) {
      cid = Cypress.$('.ag-header-viewport').find('.ag-header-row').eq(headerRow).find('.ag-header-cell')
        .eq(c)
        .attr('col-id');
      cy.task('log', `${searchTerm} - colId: ${cid}`);
      break;
    }
  }
  return cid;
}


export function findHeaderRow(rowType) {
  const headerRows = Cypress.$('.ag-header-viewport').find('.ag-header-row').length;

  let rowNum = -1;
  for (let x = 0; x < headerRows; x += 1) {
    switch (rowType) {
    case 'name':
      if (Cypress.$('.ag-header-viewport').find('.ag-header-row').eq(x).find('.ag-cell-label-container').length > 0) {
        rowNum = x;
      }
      break;
    case 'filter':
      if (Cypress.$('.ag-header-viewport').find('.ag-header-row').eq(x).find('.ag-floating-filter-full-body').length > 0) {
        rowNum = x;
      }
      break;
    default:
    }
  }
  return rowNum;
}


export function isMenuPopupOpen() {
  let isOpen = false;
  const filterPop = Cypress.$('body').find('.ag-popup').length;
  if (filterPop > 0) {
    isOpen = true;
  }
  return isOpen;
}


//* *****************************Table Rows Methods ******************************************** */
export function getTableRowCount(tableNum) {
  if (typeof tableNum === 'undefined') { tableNum = 0; }
  let rowCount = 0;
  scrollTableVertically('bottom');
  const rows = Cypress.$('.ag-center-cols-clipper').eq(tableNum).find('.ag-row').length;
  for (let x = 0; x < rows; x += 1) {
    const currentIndex = Cypress.$('.ag-center-cols-clipper').eq(tableNum).find('.ag-row').eq(x)
      .attr('row-index');
    if (currentIndex > rowCount) { rowCount = currentIndex; }
  }
  cy.task('log', `rows found: ${rowCount}`);
  return rowCount;
}
export function verifyColumnHoverAllValues(colId) {
  const error = [];
  const actual = [];
  let totalRows = 0;
  cy.get('body').then(() => {
    scrollTableVertically('bottom');
  }).then(() => {
    cy.get('.ag-center-cols-clipper').find('.ag-row').each((currentRow) => {
      const currRowIndex = currentRow.attr('row-index');
      if (currRowIndex > totalRows) {
        totalRows = currRowIndex;
      }
    });
  })
    .then(() => {
      const vscroll = getArrayOfScrollsFromValue(totalRows);
      cy.wrap(vscroll).each((currentScroll) => {
        cy.get('body').then(() => {
          cy.get('.ag-center-cols-clipper').find(`[col-id=${colId}]`).each((currentCell) => {
            const currentValue = currentCell.text().replace(/\s\s+/g, ' ').trim();
            if ((!actual.includes(currentValue)) && (currentValue.length > 0) && (currentValue !== '-')) { actual.push(currentValue); }
          });
        }).then(() => {
          scrollTableVertically(currentScroll);
        });
      });
    })
    .then(() => {
      cy.task('log', `${colId} Column Values: ${actual}`);

      cy.wrap(actual).each((val) => {
        cy.get('body').then(() => {
          scrolltoColumnValue(colId, val);
        }).then(() => {
          cy.get('.ag-center-cols-clipper').find(`[col-id=${colId}]`).each((currentCell) => {
            const currentVal = currentCell.text().replace(/\s\s+/g, ' ').trim();
            if (currentVal === val) {
              const rowIndex = currentCell.parent().attr('row-index');
              cy.get(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).then((columnValue) => {
                const pops = columnValue.find('.display-text-with-popover').length;
                if (pops > 0) {
                  cy.get('body').then(() => {
                    cy.wrap(columnValue).find('.ag-cell-value').click();
                    cy.wrap(columnValue).find('.ag-cell-value').click();
                    cy.wrap(columnValue).find('.ag-cell-value').trigger('mouseenter', {force: true});
                  })
                    .then(() => {
                      cy.get(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).find('.display-text-with-popover').parent()
                        .then((currentTooltip) => {
                          const tooltip = currentTooltip.attr('aria-describedby');
                          cy.get(`[id=${tooltip}]`).then((toolText) => {
                            if (toolText.text().length === 0) { error.push(currentVal); }
                          });
                        });
                    }).then(() => {
                      cy.get(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).find('.ag-cell-value').click();
                    })
                    .then(() => {
                      cy.get(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).find('.ag-cell-value').click();
                    })
                    .then(() => {
                      cy.get(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).find('.ag-cell-value').trigger('mouseout', {force: true});
                    });
                } else if (!error.includes(currentVal) && currentVal !== '-' && currentVal !== '') {
                  error.push(currentVal);
                }
              });
              return false;
            }
          });
        });
      });
    })
    .then(() => {
      cy.task('log', `Errors: ${error}`);
      cy.wrap(error).should('have.length', 0, `errors: ${JSON.stringify(error)}`);
    });
}


export function verifyCellHover(colId, tableNum) {
  if (typeof tableNum === 'undefined') { tableNum = 0; }
  cy.get('.ag-center-cols-viewport').eq(tableNum).find(`[col-id=${colId}]`).each((currentColumn) => {
    const labels = currentColumn.find('.popoverLabel').length;
    if (labels > 0) {
      cy.get('body').then(() => {
        cy.wrap(currentColumn).find('.popoverLabel').click({force: true});
      }).then(() => {
        cy.wrap(currentColumn).find('.popoverLabel').trigger('mouseover', {force: true});
        // eslint-disable-next-line cypress/no-unnecessary-waiting
        cy.wait(500);
      }).then(() => {
        cy.get('.show').not('.dropdown-menu').then((popover) => {
          cy.task('log', popover.text());
          cy.wrap(popover).should('have.length', 1);
          cy.wrap(popover).invoke('text').should('have.length.gt', 1);
        });
      })
        .then(() => {
          cy.wrap(currentColumn).find('.popoverLabel').click({force: true});
        })
        .then(() => {
          cy.wrap(currentColumn).find('.popoverLabel').trigger('mouseout', {force: true});
        });
    }
  });
}


export function verifyColumnHoverByValues(colId, actual) {
  const error = [];
  cy.get('body').then(() => {
    cy.wrap(actual).each((val) => {
      cy.get('body').then(() => {
        scrolltoColumnValue(colId, val.value);
      }).then(() => {
        let found = false;
        cy.get('body').then(() => {
          cy.get('.ag-center-cols-clipper').find(`[col-id=${colId}]`).each((currentCell) => {
            const errorJson = {};
            const currentVal = currentCell.text().replace(/\s\s+/g, ' ').trim();
            if (currentVal === val.value) {
              found = true;
              const rowIndex = currentCell.parent().attr('row-index');
              cy.get('body').then((pageBody) => {
                const pb = pageBody.find('.popover-body').length;
                if (pb > 0) { cy.get('.popover-body').click({force: true}); }
              }).then(() => {
                cy.get(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).then((columnValue) => {
                  const pops = columnValue.find('.display-text-with-popover').length;
                  const hoverModal = columnValue.find('.hazard-source-modal-container').length;
                  if (pops > 0) {
                    cy.get('body').then(() => {
                      cy.get(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).find('.ag-cell-value').click();
                    }).then(() => {
                      cy.get(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).find('.ag-cell-value').click();
                    }).then(() => {
                      cy.get(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).find('.ag-cell-value').trigger('mouseenter', {force: true});
                      // eslint-disable-next-line cypress/no-unnecessary-waiting
                      cy.wait(2000);
                    })
                      .then(() => {
                        cy.get(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).find('.display-text-with-popover').parent()
                          .then((currentTooltip) => {
                            const tooltip = currentTooltip.attr('aria-describedby');
                            cy.task('log', `tooltip: ${tooltip}`);
                            if (typeof tooltip === 'undefined') {
                              errorJson.columnValue = val.value;
                              errorJson.actual = 'no hovertext is shown';
                              errorJson.expected = val.hoverText;
                              error.push(errorJson);
                            } else {
                              cy.get(`[id=${tooltip}]`).then((toolText) => {
                                if (!toolText.text().includes(val.hoverText)) {
                                  errorJson.columnValue = val.value;
                                  errorJson.actual = toolText.text();
                                  errorJson.expected = val.hoverText;
                                  error.push(errorJson);
                                  cy.task('log', `text: ${toolText.text()}`);
                                }
                              });
                            }
                          })
                          .then(() => {
                            cy.get(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).find('.ag-cell-value')
                              .trigger('mouseout', {force: true});
                          })
                          .then(() => {
                            cy.get(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).find('.ag-cell-value').trigger('mouseleave', {force: true});
                          })
                          .then(() => {
                            cy.get('body').then((pageBody) => {
                              const pb = pageBody.find('.popover-body').length;
                              if (pb > 0) { cy.get(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).find('.ag-cell-value').click({force: true}); }
                            });
                          });
                      });
                  } else if (hoverModal > 0) {
                    cy.get('body').then(() => {
                      cy.get(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).find('.ag-cell-value').rightclick();
                    }).then(() => {
                      cy.get(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).find('.ag-cell-value').trigger('mouseenter', {force: true});
                    })
                      .then(() => {
                        cy.get(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).find('.hazard-source-modal-container').then((currentTooltip) => {
                          const tooltip = currentTooltip.attr('aria-describedby');
                          cy.get(`[id=${tooltip}]`).then((toolText) => {
                            if (!toolText.text().includes(val.hoverText)) {
                              errorJson.columnValue = val.value;
                              errorJson.actual = toolText.text();
                              errorJson.expected = val.hoverText;
                              error.push(errorJson);
                            }
                          });
                        });
                      })
                      .then(() => {
                        cy.get(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).find('.ag-cell-value').trigger('mouseout', {force: true});
                      })
                      .then(() => {
                        cy.get(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).find('.ag-cell-value').trigger('mouseleave', {force: true});
                      })
                      .then(() => {
                        cy.get('body').then((pageBody) => {
                          const pb = pageBody.find('.popover-body').length;
                          if (pb > 0) { cy.get(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).find('.ag-cell-value').click({force: true}); }
                        });
                      })
                      .then(() => {
                        if (typeof val.modalText !== 'undefined') {
                          cy.get('body').then(() => {
                            cy.get(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).find('.ag-cell-value').click();
                          })

                            .then(() => {
                              cy.get('body').then((pageBody) => {
                                const popup = pageBody.find('.modal-dialog').length;
                                if (popup > 0) {
                                  cy.get('.modal-dialog').then((modalText) => {
                                    if (!modalText.text().includes(val.modalText)) {
                                      errorJson.columnValue = val.value;
                                      errorJson.actual = modalText.text();
                                      errorJson.expected = val.modalText;
                                      error.push(errorJson);
                                    }
                                  }).then(() => {
                                    cy.get('.modal-dialog').find('.close').click();
                                  });
                                } else {
                                  errorJson.columnValue = val.value;
                                  errorJson.actual = 'No modal found';
                                  errorJson.expected = val.hoverText;
                                  error.push(errorJson);
                                }
                              });
                            });
                        }
                      });
                  } else if (!error.includes(currentVal) && currentVal !== '-' && currentVal !== '') {
                    errorJson.columnValue = val.value;
                    errorJson.actual = currentVal.text();
                    errorJson.expected = val.hoverText;
                    error.push(errorJson);
                  }
                });
              });

              return false;
            }
          });
        }).then(() => {
          if (!found) {
            const errorJson = {};
            errorJson.columnValue = val.value;
            errorJson.actual = 'Value not found';
            errorJson.expected = val.hoverText;
            error.push(errorJson);
          }
        });
      });
    });
  }).then(() => {
    cy.task('log', `Errors: ${JSON.stringify(error)}`);
    cy.wrap(error).should('have.length', 0, `errors: ${JSON.stringify(error)}`);
  });
}


export function writeTableRowsToJson() {
  let totalRows = 0;
  let tableJson = {};
  cy.get('body').then(() => {
    scrollTableVertically('bottom');
  }).then(() => {
    cy.get('.ag-center-cols-clipper').find('.ag-row').each((currentRow) => {
      const currRowIndex = currentRow.attr('row-index');
      if (currRowIndex > totalRows) {
        totalRows = currRowIndex;
      }
    });
  }).then(() => {
    tableJson.totalRows = totalRows;
  })
    .then(() => {
      writeToFile('cypress/fixtures/tableInfo.json', tableJson);
    });
}

export function readTableInfoFromJson() {
  readValueFromJsonFile('cypress/fixtures/tableInfo.json', 'totalRows');
}

export function verifyAllColumnValuesByCategory(colId, expectedCategory, values) {
  let category = '';
  const error = [];
  const autoName = getAutoColumnId();
  const vscroll = createListOfPercentages(1);
  cy.get('body').then(() => {
    cy.wrap(vscroll).each((percent) => {
      cy.get('body').then(() => {
        scrollTableVertically(percent);
      }).then(() => {
        cy.get('.ag-center-cols-clipper').find('.ag-row').each((currentRow) => {
          const rowIndex = currentRow.attr('row-index');
          const currentCat = currentRow.find(`[col-id=${autoName}]`).text().replace(/\s\s+/g, ' ').trim();
          if (currentCat.length > 0) {
            category = currentCat;
          }
          scrollToColumn(colId, 'id');

          cy.get('.ag-center-cols-clipper').find(`[row-index=${rowIndex}]`).find(`[col-id=${colId}]`).then((currentCell) => {
            const currentVal = currentCell.text().replace(/\s\s+/g, ' ').trim();
            if (!values.includes(currentVal) && !error.includes(currentVal) && currentVal !== '-' && currentVal !== '' && category.includes(expectedCategory)) {
              error.push(currentVal);
            }
          });
        });
      });
    });
  }).then(() => {
    cy.wrap(error).should('have.length', 0, `errors: ${JSON.stringify(error)}`);
  }).then(() => {
    scrollHorizontally('left');
  })
    .then(() => {
      cy.get('.ag-body-viewport').scrollTo('topLeft', {ensureScrollable: false});
    });
}

export function verifyAllColumnValues(colId, expectedValues) {
  const error = [];
  let vscroll = [];
  let numRows = 0;
  const actualValues = [];

  if (typeof expectedValues !== 'undefined' && expectedValues.length > 0) {
    cy.get('body').then(() => {
      scrollTableVertically('bottom');
    }).then(() => {
      cy.get('.ag-center-cols-clipper').find('.ag-row').each((currentRow) => {
        numRows = currentRow.attr('row-index');
      });
    }).then(() => {
      vscroll = getArrayOfScrollsFromValue(numRows);
    })
      .then(() => {
        cy.wrap(vscroll).each((percent) => {
          cy.get('body').then(() => {
            scrollTableVertically(percent);
          }).then(() => {
            scrollToColumn(colId, 'id');
          })
            .then(() => {
              cy.get('.ag-center-cols-clipper').find(`[col-id=${colId}]`).each((currentCell) => {
                const currentVal = currentCell.text().replace(/\s\s+/g, ' ').trim();
                if (!actualValues.includes(currentVal) && currentVal.length > 0 && currentVal !== '-') {
                  actualValues.push(currentVal);
                }
              });
            });
        });
      })
      .then(() => {
        cy.wrap(actualValues).each((currentValue) => {
          if (!expectedValues.includes(currentValue)) { error.push(currentValue); }
        });
      })
      .then(() => {
        cy.wrap(error).should('have.length', 0, `errors: ${JSON.stringify(error)}`);
      });
  }
}

export function verifyColumnValuesExist(colId, expectedValues) {
  const error = [];
  let vscroll = [];
  let numRows = 0;
  const actualValues = [];
  cy.get('body').then(() => {
    scrollTableVertically('bottom');
  }).then(() => {
    cy.get('.ag-center-cols-clipper').find('.ag-row').each((currentRow) => {
      numRows = currentRow.attr('row-index');
    });
  }).then(() => {
    vscroll = getArrayOfScrollsFromValue(numRows);
  })
    .then(() => {
      cy.wrap(vscroll).each((percent) => {
        cy.get('body').then(() => {
          scrollTableVertically(percent);
        }).then(() => {
          scrollToColumn(colId, 'id');
        }).then(() => {
          cy.get('.ag-center-cols-clipper').find(`[col-id=${colId}]`).each((currentCell) => {
            const currentVal = currentCell.text().replace(/\s\s+/g, ' ').trim();
            if (!actualValues.includes(currentVal) && currentVal.length > 0 && currentVal !== '-') {
              actualValues.push(currentVal);
            }
          });
        });
      });
    })
    .then(() => {
      cy.wrap(expectedValues).each((currentValue) => {
        if (!actualValues.includes(currentValue)) { error.push(currentValue); }
      });
    })
    .then(() => {
      cy.wrap(error).should('have.length', 0, `errors: ${JSON.stringify(error)}`);
    })
    .then(() => {
      scrollHorizontally('left');
    })
    .then(() => {
      cy.get('.ag-body-viewport').scrollTo('topLeft', {ensureScrollable: false});
    });
}


//* **************************Expand Methods ********************************** */
export function getSetRowIndexExpandState(rowNum, expanded, getSet) {
  const autoName = getAutoColumnId();
  cy.get('.ag-body-viewport').then(() => {
    scrollToRowIndex(rowNum);
  }).then(() => {
    cy.get('.ag-center-cols-clipper').find(`[row-index=${rowNum}]`).find(`[col-id=${autoName}]`).then((currentName) => {
      cy.task('log', `Expanding row index ${rowNum}: ${expanded}`);
      const exp = currentName.find('.ag-group-expanded').not('.ag-hidden').length;
      const notexp = currentName.find('.ag-group-contracted').not('.ag-hidden').length;
      switch (getSet) {
      case 'set':
        cy.get('body').then(() => {
          if (((exp > 0 && !expanded) || (notexp > 0 && expanded)) && (exp !== notexp)) {
            cy.get('.ag-center-cols-clipper').find(`[row-index=${rowNum}]`).find(`[col-id=${autoName}]`).first()
              .click({force: true});
          }
        }).then(() => {
          getSetRowIndexExpandState(rowNum, expanded, 'get');
        });
        break;
      case 'get':
        cy.get('.ag-center-cols-clipper').find(`[row-index=${rowNum}]`).find(`[col-id=${autoName}]`).find('.ag-group-expanded')
          .then((agGroupExpanded) => {
            if (expanded) { cy.wrap(agGroupExpanded).should('not.have.class', 'ag-hidden'); } else { cy.wrap(agGroupExpanded).should('have.class', 'ag-hidden'); }
          });
        break;
      default:
      }
    });
  });
}


export function getSetHierarchyRowExpandState(chemicalName, level, expanded, getSet) {
  cy.task('log', `****************************\nExpanding ${chemicalName} - ${level} - expanded: ${expanded} - getSet: ${getSet}`);
  let autoName = '';
  let columns = [];
  let chemRI = -1;
  cy.get('body').then(() => {
    scrollHorizontally('left');
  }).then(() => {
    cy.get('.ag-header-cell').each((currentHeader) => {
      const currentColumnId = currentHeader.attr('col-id');
      if (!columns.includes(currentColumnId)) { columns.push(currentColumnId); }
    });
  }).then(() => {
    const [first] = columns;
    if (columns.includes('ag-Grid-AutoColumn')) { autoName = 'ag-Grid-AutoColumn'; } else if (columns.includes('ag-Grid-AutoColumn-preferredName')) { autoName = 'ag-Grid-AutoColumn-preferredName'; } else if (columns.includes('preferredName')) { autoName = 'preferredName'; } else { autoName = first; }
  })
    .then(() => {
      scrolltoColumnValue(autoName, chemicalName);
    })
    .then(() => {
      cy.get('.ag-center-cols-clipper').then((currentTable) => {
        chemRI = currentTable.find(`[col-id=${autoName}]:contains("${chemicalName}")`).parents('.ag-row').attr('row-index');
        if (level.includes('chemical')) {
          getSetRowIndexExpandState(chemRI, expanded, getSet);
        } else {
          cy.get('body').then(() => {
            getSetRowIndexExpandState(chemRI, true, 'set');
          }).then(() => {
            scrollTableVertically('bottom');
          }).then(() => {
            cy.get('.ag-center-cols-clipper').find('.ag-row').last().then((lastRow) => {
              const vscrolls = getArrayOfScrollsFromValue(lastRow.attr('row-index'));
              cy.wrap(vscrolls).each((currentScroll) => {
                let finished = false;
                cy.get('body').then(() => {
                  cy.get('.ag-center-cols-container .ag-row')
                    .then(rows => rows.sort((a, b) => +a.getAttribute('row-index') - +b.getAttribute('row-index'))).then((sortedRows) => {
                      cy.wrap(sortedRows).each((currentRow) => {
                        const currentRI = currentRow.attr('row-index');
                        const currentText = currentRow.find(`[col-id=${autoName}]`).text().replace(/\s\s+/g, ' ').trim();
                        if (currentRI > chemRI) {
                          if ((!currentText.includes('point')) && (!currentText.includes('toxicity')) && (currentText.length > 0)) {
                            finished = true;
                            return false;
                          }
                          if (currentText.includes(level)) {
                            finished = true;
                            getSetRowIndexExpandState(currentRI, expanded, getSet);
                            return false;
                          }
                        }
                      });
                    })
                    .then(() => {
                      if (!finished) { scrollTableVertically(currentScroll); }
                    });
                });
              });
            });
          });
        }
      });
    });
}


//* *******************************Footer Methods******************************** */
export function verifyNumberOfChemicals(type, expected) {
  cy.get('body').then(() => {
    cy.get('.ag-status-bar').scrollIntoView();
  }).then(() => {
    cy.get('.ag-status-bar-right').scrollIntoView();
  })

    .then((statusBarRight) => {
      const sbText = statusBarRight.text().replace(/\s\s+/g, ' ').trim();
      const sbTextSplit = sbText.split(' ');
      let actual = -1;

      switch (type) {
      case 'total':
        actual = parseInt(sbTextSplit[2], 10);
        break;
      case 'viewed':
        actual = parseInt(sbTextSplit[0], 10);
        break;
      case 'selected':
        actual = parseInt(sbTextSplit[9], 10);
        break;
      default:
      }
      cy.task('log', `${type} - expected: ${expected} - actual: ${actual}`);
      cy.wrap(actual).should('eq', expected);
    });
}

export function verifyTableFooterSearchResults(type, expected) {
  cy.get('body').then(() => {
    cy.get('.ag-status-bar').scrollIntoView();
  }).then(() => {
    cy.get('.ag-status-bar-right').scrollIntoView();
  })
    .then(() => {
      switch (type) {
      case 'rows':
        cy.get('.ag-status-panel-total-and-filtered-row-count').find('.ag-status-name-value-value').should('have.text', expected);
        break;
      case 'searched':
        cy.get('.unmatchedStatus').eq(0).then((statusObject) => {
          const statusText = statusObject.text().replace(/\s\s+/g, ' ').trim().split(' ');
          cy.wrap(statusText).should('include', expected);
        });
        break;
      case 'matched':
        cy.get('.unmatchedStatus').eq(1).then((statusObject) => {
          const statusText = statusObject.text().replace(/\s\s+/g, ' ').trim().split(' ');
          cy.wrap(statusText).should('include', expected);
        });
        break;
      case 'selected':
        if (expected > 0) {
          cy.get('.ag-status-panel-selected-row-count').then((statusObject) => {
            const statusText = statusObject.text().replace(/\s\s+/g, ' ').trim().split(' ');
            cy.wrap(statusText).should('include', expected);
          });
        } else {
          cy.get('.ag-status-panel-selected-row-count').should('have.class', 'ag-hidden');
        }
        break;
      case 'selected-unmatched':
        if (expected !== 0) {
          cy.get('.unmatchedStatus').eq(2).then((statusObject) => {
            const statusText = statusObject.text().replace(/\s\s+/g, ' ').trim().split(' ');
            cy.wrap(statusText).should('include', expected);
          });
        } else {
          cy.get('.unmatchedStatus').should('have.length', 2);
        }
        break;
      default:
      }
    });
}

export function verifyTableCell(rowNum, colId, expected) {
  cy.task('log', `*************************Verifying Table Cell: Row: ${rowNum} - colId: ${colId} - expected: ${expected}`);
  cy.get('body').then(() => {
    scrollToColumn(colId, 'id');
  }).then(() => {
    if (expected.length === 0) {
      cy.get('.ag-center-cols-container').find(`[row-index=${rowNum}]`).find(`[col-id=${colId}]`).invoke('text')
        .invoke('replace', '\n', '')
        .invoke('trim')
        .should('have.length', 0);
    } else {
      cy.get('.ag-center-cols-container').find(`[row-index=${rowNum}]`).find(`[col-id=${colId}]`).should('contain.text', expected);
    }
  });
}

export function getAutoColumnId() {
  // cy.task('log', '***************Get AutoColumn colId*********');
  const columns = getTableColumns('id');
  const [first] = columns;
  let auto = 'ag-Grid-AutoColumn';
  if (columns.includes('ag-Grid-AutoColumn')) { auto = 'ag-Grid-AutoColumn'; } else if (columns.includes('ag-Grid-AutoColumn-preferredName')) { auto = 'ag-Grid-AutoColumn-preferredName'; } else if (columns.includes('preferredName')) { auto = 'preferredName'; } else { auto = first; }
  return auto;
}

export function clickPodTab(tabName) {
  cy.get('body').then(() => {
    cy.get('.v-breadcrumbs').contains('.v-breadcrumbs__item', tabName).click();
  }).then(() => {
    cy.get('.v-breadcrumbs').contains('.v-breadcrumbs__item', tabName).click();
  }).then(() => {
    cy.get('.v-breadcrumbs').contains('.v-breadcrumbs__item', tabName).type('{enter}');
  })
    .then(() => {
      cy.get('.v-breadcrumbs').contains('.v-breadcrumbs__item', tabName).click();
    })
    .then(() => {
      cy.get('.v-breadcrumbs').contains('.breadcrumbItemPod', tabName).should('have.class', 'activeBreadcrumbItem');
    });
}

export function verifyPodTabEnabled(tabName, enabled) {
  cy.get('body').then(() => {
    cy.scrollTo('topLeft', {ensureScrollable: false});
  }).then(() => {
    if (enabled) {
      cy.contains('.v-breadcrumbs__item', tabName).should('not.have.class', 'v-breadcrumbs__item--disabled');
    } else {
      cy.contains('.v-breadcrumbs__item', tabName).should('have.class', 'v-breadcrumbs__item--disabled');
    }
  });
}
