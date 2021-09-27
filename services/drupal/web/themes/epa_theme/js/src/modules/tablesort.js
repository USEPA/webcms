import tablesort from 'tablesort/src/tablesort.js';

export default function() {
  const tables = document.querySelectorAll('.usa-table--sortable');
  tables.forEach(table => {
    tablesort(table);
    table.addEventListener('beforeSort', () => {
      table
        .querySelectorAll('[data-sort-active]')
        .forEach(tableCell => tableCell.removeAttribute('data-sort-active'));
    });
    table.addEventListener('afterSort', () => {
      const selectedHeader = table.querySelector('[aria-sort]');
      if (selectedHeader) {
        const column = selectedHeader.cellIndex;
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row =>
          row.cells[column].setAttribute('data-sort-active', 'true')
        );
      }
    });
  });
}
