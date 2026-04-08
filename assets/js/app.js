'use strict';

// ============================================================
// FarmFlow — app.js
// ============================================================

const THEME_KEY = 'farmflow_theme';

// ── Theme Manager ──────────────────────────────────────────
const ThemeManager = (() => {
  const html = document.documentElement;

  // Apply a theme: set attribute, save to localStorage, update button UI
  function apply(theme, persist) {
    html.dataset.theme = theme;
    localStorage.setItem(THEME_KEY, theme);
    _updateBtn(theme);

    // Persist to server — only when user explicitly clicks, not on init
    if (persist) {
      fetch(window.APP_URL + '/api/set_theme.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ theme })
      }).catch(() => {});
    }

    // Tell charts (dashboard, accounting) to rebuild with new colours
    document.dispatchEvent(new CustomEvent('themechange', { detail: { theme } }));
  }

  function _updateBtn(theme) {
    const btn = document.getElementById('themeToggle');
    if (!btn) return;
    const label = btn.querySelector('span');
    const icon  = btn.querySelector('svg');
    if (label) label.textContent = theme === 'dark' ? 'Light' : 'Dark';
    if (icon) {
      icon.innerHTML = theme === 'dark'
        // Sun — click to go Light
        ? '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>'
        // Moon — click to go Dark
        : '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';
    }
  }

  function get() {
    return localStorage.getItem(THEME_KEY) || html.dataset.theme || 'light';
  }

  function init() {
    // 1. Silently apply saved preference (no server call, no event — just DOM)
    const saved = get();
    html.dataset.theme = saved;
    _updateBtn(saved);

    // 2. Wire up toggle button
    document.getElementById('themeToggle')?.addEventListener('click', () => {
      apply(get() === 'dark' ? 'light' : 'dark', true); // persist=true
    });
  }

  return { init, apply, get };
})();

// ── Sidebar Manager ────────────────────────────────────────
const SidebarManager = (() => {
  function open() {
    document.getElementById('sidebar')?.classList.add('open');
    document.getElementById('sidebarOverlay')?.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function close() {
    document.getElementById('sidebar')?.classList.remove('open');
    document.getElementById('sidebarOverlay')?.classList.remove('open');
    document.body.style.overflow = '';
  }
  function init() {
    document.getElementById('sidebarToggle')?.addEventListener('click', () => {
      document.getElementById('sidebar')?.classList.contains('open') ? close() : open();
    });
    document.getElementById('sidebarOverlay')?.addEventListener('click', close);
    window.addEventListener('resize', () => { if (window.innerWidth > 768) close(); });
  }
  return { init, open, close };
})();

// ── Readiness / Weeks Left ─────────────────────────────────
const WeeksCalc = (() => {
  function daysLeft(startDate, maturityDays) {
    const start    = new Date(startDate);
    const maturity = new Date(start.getTime() + maturityDays * 86400000);
    const today    = new Date(); today.setHours(0,0,0,0);
    return Math.round((maturity - today) / 86400000);
  }
  function label(startDate, maturityDays) {
    const d = daysLeft(startDate, maturityDays);
    if (d <= 0) return 'Ready';
    const w = Math.floor(d / 7), r = d % 7;
    if (w === 0) return r + 'd left';
    return r > 0 ? w + 'w ' + r + 'd' : w + 'w left';
  }
  function pct(startDate, maturityDays) {
    const elapsed = Math.round((new Date() - new Date(startDate)) / 86400000);
    return Math.min(100, Math.round((elapsed / maturityDays) * 100));
  }
  function renderAll() {
    document.querySelectorAll('[data-start-date][data-maturity-days]').forEach(el => {
      const s = el.dataset.startDate;
      const m = parseInt(el.dataset.maturityDays, 10);
      const days = daysLeft(s, m);
      const p    = pct(s, m);
      const cls  = days <= 0 ? 'success' : days <= 14 ? 'warning' : 'info';
      const bar  = el.querySelector('.ff-progress-bar');
      if (bar) { bar.style.width = p + '%'; bar.className = 'ff-progress-bar ' + cls; }
      const badge = el.querySelector('.readiness-badge, .readiness-chip');
      if (badge) badge.textContent = label(s, m);
      const fill = el.querySelector('.prog-fill');
      if (fill) {
        fill.style.width = p + '%';
        fill.className = 'prog-fill ' + (days <= 0 ? 'p-ready' : days <= 14 ? 'p-soon' : 'p-growing');
      }
    });
    document.querySelectorAll('.prog-fill[data-pct]').forEach(el => {
      setTimeout(() => { el.style.width = el.dataset.pct + '%'; }, 300);
    });
  }
  return { daysLeft, label, pct, renderAll };
})();

// ── Table Sorter ───────────────────────────────────────────
const TableSorter = (() => {
  function init(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const headers = table.querySelectorAll('th[data-sortable]');
    let sortCol = null, sortDir = 1;
    headers.forEach((th, i) => {
      th.innerHTML += ' <span style="opacity:.35;font-size:10px">⇅</span>';
      th.addEventListener('click', () => {
        sortDir = sortCol === i ? sortDir * -1 : 1;
        sortCol = i;
        const tbody = table.querySelector('tbody');
        Array.from(tbody.querySelectorAll('tr'))
          .sort((a, b) => {
            const av = a.cells[i]?.textContent.trim() || '';
            const bv = b.cells[i]?.textContent.trim() || '';
            const an = parseFloat(av.replace(/[^0-9.-]/g, ''));
            const bn = parseFloat(bv.replace(/[^0-9.-]/g, ''));
            return (!isNaN(an) && !isNaN(bn) ? an - bn : av.localeCompare(bv)) * sortDir;
          })
          .forEach(r => tbody.appendChild(r));
        headers.forEach((h, j) => {
          const ic = h.querySelector('span');
          if (ic) ic.textContent = j === i ? (sortDir === 1 ? ' ↑' : ' ↓') : ' ⇅';
        });
      });
    });
  }
  return { init };
})();

// ── Live Search ────────────────────────────────────────────
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

// ── Confirm Delete ─────────────────────────────────────────
function confirmDelete(formId, msg) {
  if (confirm(msg || 'Delete this record? This cannot be undone.')) {
    document.getElementById(formId)?.submit();
  }
}

// ── Money formatter ────────────────────────────────────────
function formatMoney(n) {
  return '₦' + Math.round(Number(n)).toLocaleString('en-NG');
}

// ── Chart.js colour helpers (used by inline scripts in dashboard & accounting) ──
const ChartDefaults = (() => {
  function isDark() { return document.documentElement.dataset.theme === 'dark'; }
  function colors() {
    const dark = isDark();
    return {
      text:   dark ? 'rgba(228,242,232,0.5)'  : 'rgba(26,46,26,0.45)',
      grid:   dark ? 'rgba(100,180,130,0.07)' : 'rgba(39,97,64,0.06)',
      tip:    dark ? 'rgba(10,20,14,0.96)'    : 'rgba(4,12,8,0.93)',
      brand:  '#2d6a4f',
      accent: '#c47d0e',
      blue:   '#2563eb',
      purple: '#7c3aed',
    };
  }
  function baseOptions() {
    const c = colors();
    const f = { family: "'Inter', system-ui, sans-serif", size: 11 };
    return {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 800, easing: 'easeInOutQuart' },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: c.tip,
          titleColor: '#d4ead9',
          bodyColor:  'rgba(212,234,217,0.7)',
          borderColor:'rgba(100,180,130,0.2)',
          borderWidth: 1,
          padding: 12,
          cornerRadius: 10,
          titleFont: { ...f, weight: '600', size: 12 },
          bodyFont: f,
        },
      },
      scales: {
        x: {
          ticks:  { color: c.text, font: f, maxRotation: 0, padding: 6 },
          grid:   { color: c.grid, drawTicks: false },
          border: { display: false },
        },
        y: {
          ticks:  { color: c.text, font: f, padding: 6 },
          grid:   { color: c.grid },
          border: { display: false },
          beginAtZero: true,
        },
      },
    };
  }
  return { isDark, colors, baseOptions };
})();

// ── DOM Ready ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  ThemeManager.init();
  SidebarManager.init();
  WeeksCalc.renderAll();
});