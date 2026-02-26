import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';

// ── Global Search (topbar autocomplete) ──────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const input    = document.getElementById('global-search-input');
  const wrapper  = document.getElementById('global-search-wrapper');
  const dropdown = document.getElementById('global-search-dropdown');
  const results  = document.getElementById('global-search-results');
  const loading  = document.getElementById('global-search-loading');
  const empty    = document.getElementById('global-search-empty');

  if (!input || !dropdown) return;           // not on pages with the topbar

  let debounceTimer = null;
  let activeIndex   = -1;

  /* ── helpers ─────────────────────────────── */
  const show = el => el.classList.remove('hidden');
  const hide = el => el.classList.add('hidden');

  function closeDropdown () {
    hide(dropdown);
    activeIndex = -1;
  }

  function highlightItem (idx) {
    const items = results.querySelectorAll('[data-search-item]');
    items.forEach(i => i.style.background = '');
    if (items[idx]) {
      items[idx].style.background = '#f3f5fa';
      items[idx].scrollIntoView({ block: 'nearest' });
    }
    activeIndex = idx;
  }

  /* ── render results into dropdown ────────── */
  function renderResults (data) {
    results.innerHTML = '';
    hide(loading);

    if (data.length === 0) {
      show(empty);
      return;
    }

    hide(empty);

    data.forEach((item, idx) => {
      const a = document.createElement('a');
      a.href = item.url;
      a.setAttribute('data-search-item', idx);
      a.className = 'flex items-center gap-3 px-4 py-2.5 transition-all duration-100 cursor-pointer';
      a.style.cssText = 'text-decoration:none;border-bottom:1px solid #f0f3f7;';
      a.innerHTML = `
        <span style="font-size:20px;width:28px;text-align:center;flex-shrink:0;">${item.icon}</span>
        <div class="flex-1 min-w-0">
          <div style="font-size:13px;font-weight:500;color:#293855;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${item.title}</div>
          <div style="font-size:11px;color:#6b7a8d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${item.subtitle}</div>
        </div>
        <span class="flex-shrink-0 px-2 py-0.5 rounded text-xs" style="background:#f0f3f7;color:#6b7a8d;font-size:10px;text-transform:uppercase;">${item.type}</span>
      `;
      a.addEventListener('mouseenter', () => highlightItem(idx));
      results.appendChild(a);
    });

    // "View all results" link
    const viewAll = document.createElement('a');
    viewAll.href = '/search?q=' + encodeURIComponent(input.value);
    viewAll.className = 'flex items-center justify-center gap-1 px-4 py-2.5';
    viewAll.style.cssText = 'font-size:12px;font-weight:500;color:#4265d6;text-decoration:none;background:#f9fafb;';
    viewAll.innerHTML = 'View all results <i class="fas fa-arrow-right" style="font-size:10px;"></i>';
    results.appendChild(viewAll);

    show(dropdown);
  }

  /* ── fetch autocomplete ──────────────────── */
  function fetchResults (query) {
    if (query.length < 2) { closeDropdown(); return; }

    show(dropdown);
    show(loading);
    hide(empty);
    results.innerHTML = '';

    fetch('/search/autocomplete?q=' + encodeURIComponent(query), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => renderResults(data))
    .catch(() => { hide(loading); show(empty); });
  }

  /* ── input events ────────────────────────── */
  input.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchResults(input.value.trim()), 250);
  });

  input.addEventListener('focus', () => {
    if (input.value.trim().length >= 2) fetchResults(input.value.trim());
  });

  /* ── keyboard navigation ─────────────────── */
  input.addEventListener('keydown', (e) => {
    const items = results.querySelectorAll('[data-search-item]');
    if (!items.length) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      highlightItem(activeIndex < items.length - 1 ? activeIndex + 1 : 0);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      highlightItem(activeIndex > 0 ? activeIndex - 1 : items.length - 1);
    } else if (e.key === 'Enter' && activeIndex >= 0) {
      e.preventDefault();
      items[activeIndex].click();
    } else if (e.key === 'Escape') {
      closeDropdown();
      input.blur();
    }
  });

  /* ── close on outside click ──────────────── */
  document.addEventListener('click', (e) => {
    if (!wrapper.contains(e.target)) closeDropdown();
  });

  /* ── Ctrl+K shortcut ────────────────────── */
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
      e.preventDefault();
      input.focus();
      input.select();
    }
  });
});
