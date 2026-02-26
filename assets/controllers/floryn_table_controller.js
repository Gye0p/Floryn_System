import { Controller } from '@hotwired/stimulus';

/**
 * Floryn Garden — Custom Table Controller
 * 
 * A native, lightweight replacement for DataTables that matches our design system.
 * Supports search, sorting, pagination, column filters, and CSV/clipboard export.
 *
 * Usage:
 *   <div data-controller="floryn-table"
 *        data-floryn-table-page-size-value="10"
 *        data-floryn-table-sort-col-value="0"
 *        data-floryn-table-sort-dir-value="asc">
 *     <table data-floryn-table-target="table"> ... </table>
 *   </div>
 */
export default class extends Controller {
  static targets = ['table', 'search', 'pageSize', 'info', 'pagination', 'columnFilter'];

  static values = {
    pageSize:  { type: Number, default: 10 },
    sortCol:   { type: Number, default: 0 },
    sortDir:   { type: String, default: 'asc' },
    noSortCols: { type: Array, default: [] },   // column indices that cannot be sorted (e.g. Actions)
    entityName: { type: String, default: 'entries' }
  };

  /* ────────── lifecycle ────────── */

  connect() {
    this._currentPage = 1;
    this._searchTerm  = '';
    this._columnFilters = {};  // { colIndex: filterValue }

    this._cacheRows();
    this._buildToolbar();
    this._bindHeaders();
    this._applySort();
    this._render();
  }

  /* ────────── internal data ────────── */

  _cacheRows() {
    const tbody = this.tableTarget.querySelector('tbody');
    // Store original rows as an array (source of truth)
    this._allRows = Array.from(tbody.querySelectorAll('tr'));
    this._filteredRows = [...this._allRows];
  }

  /* ────────── toolbar (search + page-size + export) ────────── */

  _buildToolbar() {
    // The toolbar is already in the Twig template — just wire targets.
    // Nothing to build dynamically; all wiring happens through Stimulus targets.
  }

  /* ────────── header sort binding ────────── */

  _bindHeaders() {
    const ths = this.tableTarget.querySelectorAll('thead th');
    ths.forEach((th, i) => {
      if (this.noSortColsValue.includes(i)) {
        th.classList.add('fg-no-sort');
        return;
      }
      th.classList.add('fg-sortable');
      th.dataset.colIndex = i;
      th.addEventListener('click', () => this._onHeaderClick(i));
      // Set initial indicator
      this._setSortIndicator(th, i);
    });
  }

  _onHeaderClick(colIndex) {
    if (colIndex === this.sortColValue && this.sortDirValue === 'asc') {
      this.sortDirValue = 'desc';
    } else if (colIndex === this.sortColValue && this.sortDirValue === 'desc') {
      this.sortDirValue = 'asc';
    } else {
      this.sortColValue = colIndex;
      this.sortDirValue = 'asc';
    }
    this._applySort();
    this._currentPage = 1;
    this._render();
    // Update header indicators
    this.tableTarget.querySelectorAll('thead th').forEach((th, i) => this._setSortIndicator(th, i));
  }

  _setSortIndicator(th, index) {
    th.classList.remove('fg-sort-asc', 'fg-sort-desc', 'fg-sort-none');
    if (this.noSortColsValue.includes(index)) return;
    if (index === this.sortColValue) {
      th.classList.add(this.sortDirValue === 'asc' ? 'fg-sort-asc' : 'fg-sort-desc');
    } else {
      th.classList.add('fg-sort-none');
    }
  }

  /* ────────── sorting ────────── */

  _applySort() {
    const col = this.sortColValue;
    const dir = this.sortDirValue === 'asc' ? 1 : -1;

    this._filteredRows.sort((a, b) => {
      const cellA = a.children[col];
      const cellB = b.children[col];
      // Use data-order attribute if present, otherwise innerText
      const vA = (cellA?.getAttribute('data-order') || cellA?.innerText || '').trim().toLowerCase();
      const vB = (cellB?.getAttribute('data-order') || cellB?.innerText || '').trim().toLowerCase();

      // Try numeric comparison
      const nA = parseFloat(vA.replace(/[^0-9.\-]/g, ''));
      const nB = parseFloat(vB.replace(/[^0-9.\-]/g, ''));
      if (!isNaN(nA) && !isNaN(nB)) return (nA - nB) * dir;

      // Try date comparison
      const dA = Date.parse(vA);
      const dB = Date.parse(vB);
      if (!isNaN(dA) && !isNaN(dB)) return (dA - dB) * dir;

      // Fallback: string comparison
      return vA.localeCompare(vB) * dir;
    });
  }

  /* ────────── search ────────── */

  onSearch(event) {
    this._searchTerm = (event.target.value || '').trim().toLowerCase();
    this._currentPage = 1;
    this._applyFilters();
    this._applySort();
    this._render();
  }

  /* ────────── column filters (from <select> elements) ────────── */

  onColumnFilter(event) {
    const el = event.target;
    const colIndex = parseInt(el.dataset.col, 10);
    const value = el.value;
    if (value) {
      this._columnFilters[colIndex] = value.toLowerCase();
    } else {
      delete this._columnFilters[colIndex];
    }
    this._currentPage = 1;
    this._applyFilters();
    this._applySort();
    this._render();
  }

  resetFilters() {
    // Reset all column filter selects
    this.columnFilterTargets.forEach(sel => { sel.value = ''; });
    this._columnFilters = {};
    // Reset search
    if (this.hasSearchTarget) this.searchTarget.value = '';
    this._searchTerm = '';
    this._currentPage = 1;
    this._applyFilters();
    this._applySort();
    this._render();
  }

  /* ────────── combined filter logic ────────── */

  _applyFilters() {
    this._filteredRows = this._allRows.filter(row => {
      // Column filters
      for (const [col, val] of Object.entries(this._columnFilters)) {
        const cell = row.children[parseInt(col)];
        const text = (cell?.innerText || '').toLowerCase();
        if (!text.includes(val)) return false;
      }
      // Search across all columns
      if (this._searchTerm) {
        const rowText = row.innerText.toLowerCase();
        if (!rowText.includes(this._searchTerm)) return false;
      }
      return true;
    });
  }

  /* ────────── page size ────────── */

  onPageSizeChange(event) {
    const val = event.target.value;
    this.pageSizeValue = val === '-1' ? 99999 : parseInt(val, 10);
    this._currentPage = 1;
    this._render();
  }

  /* ────────── pagination actions ────────── */

  goFirst()    { this._currentPage = 1; this._render(); }
  goPrev()     { if (this._currentPage > 1) { this._currentPage--; this._render(); } }
  goNext()     { if (this._currentPage < this._totalPages()) { this._currentPage++; this._render(); } }
  goLast()     { this._currentPage = this._totalPages(); this._render(); }
  goToPage(e)  { this._currentPage = parseInt(e.params.page, 10); this._render(); }

  _totalPages() {
    return Math.max(1, Math.ceil(this._filteredRows.length / this.pageSizeValue));
  }

  /* ────────── export actions ────────── */

  exportCopy() {
    const text = this._getExportText('\t');
    navigator.clipboard.writeText(text).then(() => this._toast('Copied to clipboard'));
  }

  exportCsv() {
    const text = this._getExportText(',', true);
    const blob = new Blob([text], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `${this.entityNameValue}_export.csv`;
    a.click();
    URL.revokeObjectURL(url);
    this._toast('CSV downloaded');
  }

  exportExcel() {
    // Generate a simple HTML table that Excel can open
    const headers = Array.from(this.tableTarget.querySelectorAll('thead th'))
      .filter((_, i) => !this.noSortColsValue.includes(i))
      .map(th => `<th>${th.innerText.trim()}</th>`).join('');

    const rows = this._filteredRows.map(row => {
      const cells = Array.from(row.children)
        .filter((_, i) => !this.noSortColsValue.includes(i))
        .map(td => `<td>${td.innerText.trim()}</td>`).join('');
      return `<tr>${cells}</tr>`;
    }).join('');

    const html = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body><table><thead><tr>${headers}</tr></thead><tbody>${rows}</tbody></table></body></html>`;
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `${this.entityNameValue}_export.xls`;
    a.click();
    URL.revokeObjectURL(url);
    this._toast('Excel downloaded');
  }

  exportPrint() {
    const headers = Array.from(this.tableTarget.querySelectorAll('thead th'))
      .filter((_, i) => !this.noSortColsValue.includes(i))
      .map(th => `<th style="border:1px solid #ddd;padding:8px 12px;background:#f5f7fa;font-size:11px;text-transform:uppercase;font-weight:600;">${th.innerText.trim()}</th>`).join('');

    const rows = this._filteredRows.map(row => {
      const cells = Array.from(row.children)
        .filter((_, i) => !this.noSortColsValue.includes(i))
        .map(td => `<td style="border:1px solid #eee;padding:8px 12px;font-size:13px;">${td.innerText.trim()}</td>`).join('');
      return `<tr>${cells}</tr>`;
    }).join('');

    const win = window.open('', '_blank');
    win.document.write(`<!DOCTYPE html><html><head><title>Print — ${this.entityNameValue}</title><style>body{font-family:'DM Sans',sans-serif;padding:24px;}table{border-collapse:collapse;width:100%;} @media print{body{padding:0;}}</style></head><body><h2 style="font-family:'Playfair Display',serif;color:#293855;margin-bottom:16px;">${this.entityNameValue} Report</h2><table><thead><tr>${headers}</tr></thead><tbody>${rows}</tbody></table></body></html>`);
    win.document.close();
    win.focus();
    setTimeout(() => { win.print(); }, 300);
  }

  _getExportText(separator, quoted = false) {
    const headers = Array.from(this.tableTarget.querySelectorAll('thead th'))
      .filter((_, i) => !this.noSortColsValue.includes(i))
      .map(th => {
        const v = th.innerText.trim();
        return quoted ? `"${v}"` : v;
      });

    const rows = this._filteredRows.map(row => {
      return Array.from(row.children)
        .filter((_, i) => !this.noSortColsValue.includes(i))
        .map(td => {
          const v = td.innerText.trim().replace(/\n/g, ' ');
          return quoted ? `"${v.replace(/"/g, '""')}"` : v;
        }).join(separator);
    });

    return [headers.join(separator), ...rows].join('\n');
  }

  _toast(message) {
    const el = document.createElement('div');
    el.className = 'fg-toast';
    el.textContent = message;
    document.body.appendChild(el);
    requestAnimationFrame(() => el.classList.add('show'));
    setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 300); }, 2000);
  }

  /* ────────── render ────────── */

  _render() {
    const tbody    = this.tableTarget.querySelector('tbody');
    const total    = this._filteredRows.length;
    const pageSize = this.pageSizeValue;
    const totalPages = this._totalPages();
    if (this._currentPage > totalPages) this._currentPage = totalPages;

    const start = (this._currentPage - 1) * pageSize;
    const end   = Math.min(start + pageSize, total);
    const visible = this._filteredRows.slice(start, end);

    // Clear tbody and append visible rows
    tbody.innerHTML = '';
    if (visible.length === 0) {
      const cols = this.tableTarget.querySelectorAll('thead th').length;
      const emptyRow = document.createElement('tr');
      emptyRow.innerHTML = `<td colspan="${cols}" class="fg-empty-state">
        <div class="fg-empty-icon"><i class="fas fa-inbox"></i></div>
        <div class="fg-empty-text">No matching ${this.entityNameValue} found</div>
      </td>`;
      tbody.appendChild(emptyRow);
    } else {
      visible.forEach(row => tbody.appendChild(row));
    }

    // Update info
    if (this.hasInfoTarget) {
      if (total === 0) {
        this.infoTarget.textContent = `No ${this.entityNameValue} to display`;
      } else {
        this.infoTarget.textContent = `Showing ${start + 1} to ${end} of ${total} ${this.entityNameValue}`;
      }
    }

    // Update pagination
    if (this.hasPaginationTarget) {
      this._renderPagination(totalPages);
    }
  }

  _renderPagination(totalPages) {
    const el = this.paginationTarget;
    el.innerHTML = '';

    const mkBtn = (label, action, disabled = false, active = false) => {
      const btn = document.createElement('button');
      btn.type  = 'button';
      btn.className = `fg-page-btn${active ? ' active' : ''}${disabled ? ' disabled' : ''}`;
      btn.innerHTML = label;
      if (!disabled) {
        btn.addEventListener('click', action);
      }
      return btn;
    };

    // Prev
    el.appendChild(mkBtn('<i class="fas fa-chevron-left"></i>', () => this.goPrev(), this._currentPage <= 1));

    // Page numbers with ellipsis
    const pages = this._getPageNumbers(this._currentPage, totalPages);
    pages.forEach(p => {
      if (p === '...') {
        const span = document.createElement('span');
        span.className = 'fg-page-ellipsis';
        span.textContent = '...';
        el.appendChild(span);
      } else {
        el.appendChild(mkBtn(String(p), () => { this._currentPage = p; this._render(); }, false, p === this._currentPage));
      }
    });

    // Next
    el.appendChild(mkBtn('<i class="fas fa-chevron-right"></i>', () => this.goNext(), this._currentPage >= totalPages));
  }

  _getPageNumbers(current, total) {
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    const pages = [];
    if (current <= 4) {
      pages.push(1, 2, 3, 4, 5, '...', total);
    } else if (current >= total - 3) {
      pages.push(1, '...', total - 4, total - 3, total - 2, total - 1, total);
    } else {
      pages.push(1, '...', current - 1, current, current + 1, '...', total);
    }
    return pages;
  }
}
