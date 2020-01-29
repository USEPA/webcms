import tablesort from 'tablesort/src/tablesort.js';

export default function() {
  const tables = document.querySelectorAll('.usa-table--sortable');
  tables.forEach(table => {
    tablesort(table);
  });
}
