// ============================================================
// assets/js/app.js — FarmFlow Global JavaScript
// ============================================================

'use strict';

// ============================================================
// Theme Manager
// ============================================================
const ThemeManager = (() => {
  const STORAGE_KEY = 'farmflow_theme';
  const html = document.documentElement;
  const toggleBtn = document.getElementById('themeToggle');
  const themeIcon = document.getElementById('themeIcon');
  const themeLabel = document.getElementById('themeLabel');

  function get() {
    return localStorage.getItem(STORAGE_KEY) || html.dataset.theme || 'light';
  }

  function apply(theme) {
    html.dataset.theme = theme;
    localStorage.setItem(STORAGE_KEY, theme);
    if (themeIcon) themeIcon.textContent = theme === 'dark' ? '☀️' : '🌙';
    if (themeLabel) themeLabel.textContent = theme === 'dark' ? 'Light' : 'Dark';
    // Persist to server
    fetch(`${window.APP_URL}/api/set_theme.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ theme })
    }).catch(() => {});
  }

  function toggle() {
    apply(get() === 'dark' ? 'light' : 'dark');
  }

  function init() {
    const saved = get();
    apply(saved);
    if (toggleBtn) toggleBtn.addEventListener('click', toggle);
  }

  return { init, apply, get };
})();

// ============================================================
// Sidebar Manager
// ============================================================
const SidebarManager = (() => {
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  const toggle   = document.getElementById('sidebarToggle');

  function open() {
    sidebar?.classList.add('open');
    overlay?.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function close() {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('open');
    document.body.style.overflow = '';
  }

  function init() {
    toggle?.addEventListener('click', () => {
      sidebar?.classList.contains('open') ? close() : open();
    });
    overlay?.addEventListener('click', close);

    // Close on resize to desktop
    window.addEventListener('resize', () => {
      if (window.innerWidth > 768) close();
    });
  }

  return { init, open, close };
})();

// ============================================================
// Weeks Left Calculator
// ============================================================
const WeeksCalc = (() => {

  /**
   * Calculate days remaining.
   * @param {string} startDate - ISO date string (YYYY-MM-DD)
   * @param {number} maturityDays
   * @returns {number} positive = days left, negative = overdue
   */
  function daysLeft(startDate, maturityDays) {
    const start    = new Date(startDate);
    const maturity = new Date(start.getTime() + maturityDays * 86400000);
    const today    = new Date();
    today.setHours(0, 0, 0, 0);
    return Math.round((maturity - today) / 86400000);
  }

  function weeksLeft(startDate, maturityDays) {
    const days = daysLeft(startDate, maturityDays);
    if (days <= 0) return 'Ready';
    const w = Math.floor(days / 7);
    const d = days % 7;
    if (w === 0) return `${d}d left`;
    return d > 0 ? `${w}w ${d}d left` : `${w}w left`;
  }

  function readinessPct(startDate, maturityDays) {
    const start   = new Date(startDate);
    const today   = new Date();
    const elapsed = Math.round((today - start) / 86400000);
    return Math.min(100, Math.round((elapsed / maturityDays) * 100));
  }

  function statusClass(startDate, maturityDays) {
    const days = daysLeft(startDate, maturityDays);
    if (days <= 0)  return 'success';
    if (days <= 14) return 'warning';
    return 'info';
  }

  /** Render all [data-weeks-left] elements on the page */
  function renderAll() {
    document.querySelectorAll('[data-start-date][data-maturity-days]').forEach(el => {
      const start    = el.dataset.startDate;
      const maturity = parseInt(el.dataset.maturityDays, 10);
      const label    = el.querySelector('.weeks-label');
      const bar      = el.querySelector('.ff-progress-bar');
      const pct      = readinessPct(start, maturity);
      const cls      = statusClass(start, maturity);

      if (label) label.textContent = weeksLeft(start, maturity);
      if (bar) {
        bar.style.width = `${pct}%`;
        bar.className = `ff-progress-bar ${cls}`;
      }

      // Set badge color
      const badge = el.querySelector('.readiness-badge');
      if (badge) {
        badge.className = `ff-badge ff-badge-${cls}`;
        badge.textContent = weeksLeft(start, maturity);
      }
    });
  }

  return { daysLeft, weeksLeft, readinessPct, statusClass, renderAll };
})();

// ============================================================
// Table Sorter (lightweight client-side)
// ============================================================
const TableSorter = (() => {
  function init(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const headers = table.querySelectorAll('th[data-sortable]');
    let sortCol = null, sortDir = 1;

    headers.forEach((th, i) => {
      th.style.cursor = 'pointer';
      th.innerHTML += ' <span class="sort-icon">⇅</span>';
      th.addEventListener('click', () => {
        if (sortCol === i) {
          sortDir *= -1;
        } else {
          sortCol = i;
          sortDir = 1;
        }
        sortTable(table, i, sortDir, headers);
      });
    });
  }

  function sortTable(table, col, dir, headers) {
    const tbody = table.querySelector('tbody');
    const rows  = Array.from(tbody.querySelectorAll('tr'));

    rows.sort((a, b) => {
      const aVal = a.cells[col]?.textContent.trim() || '';
      const bVal = b.cells[col]?.textContent.trim() || '';
      const aNum = parseFloat(aVal.replace(/[^0-9.-]/g, ''));
      const bNum = parseFloat(bVal.replace(/[^0-9.-]/g, ''));
      if (!isNaN(aNum) && !isNaN(bNum)) return (aNum - bNum) * dir;
      return aVal.localeCompare(bVal) * dir;
    });

    rows.forEach(r => tbody.appendChild(r));

    // Update sort icons
    headers.forEach((th, i) => {
      const icon = th.querySelector('.sort-icon');
      if (icon) icon.textContent = i === col ? (dir === 1 ? '↑' : '↓') : '⇅';
    });
  }

  return { init };
})();

// ============================================================
// Live Search (filter table rows)
// ============================================================
function initLiveSearch(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;

  input.addEventListener('input', () => {
    const q = input.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// ============================================================
// Confirm Delete
// ============================================================
function confirmDelete(formId, message) {
  message = message || 'Are you sure you want to delete this record? This action cannot be undone.';
  if (confirm(message)) {
    document.getElementById(formId)?.submit();
  }
}

// ============================================================
// Chart.js Default Config
// ============================================================
const ChartDefaults = (() => {
  function getColors() {
    const isDark = document.documentElement.dataset.theme === 'dark';
    return {
      text:    isDark ? '#a0aec0' : '#4a5568',
      grid:    isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)',
      brand:   '#2d6a4f',
      accent:  '#f4a261',
      green:   '#38a169',
      blue:    '#3182ce',
      orange:  '#e76f51',
      purple:  '#6b46c1',
    };
  }

  function baseOptions(title) {
    const c = getColors();
    return {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          labels: { color: c.text, font: { family: "'Sora', sans-serif", size: 12 } }
        },
        tooltip: {
          backgroundColor: 'rgba(15,25,35,0.9)',
          titleFont: { family: "'Sora', sans-serif" },
          bodyFont:  { family: "'Sora', sans-serif" },
          padding: 12,
          cornerRadius: 8,
        },
      },
      scales: {
        x: {
          ticks: { color: c.text, font: { family: "'Sora', sans-serif", size: 11 } },
          grid:  { color: c.grid },
        },
        y: {
          ticks: { color: c.text, font: { family: "'Sora', sans-serif", size: 11 } },
          grid:  { color: c.grid },
          beginAtZero: true,
        },
      },
    };
  }

  return { getColors, baseOptions };
})();

// ============================================================
// Number formatter
// ============================================================
function formatMoney(n) {
  return '₦' + Number(n).toLocaleString('en-NG', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

// ============================================================
// Init all on DOM ready
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
  ThemeManager.init();
  SidebarManager.init();
  WeeksCalc.renderAll();

  // Expose APP_URL to window (set via PHP in footer)
  window.APP_URL = window.APP_URL || '';
});
