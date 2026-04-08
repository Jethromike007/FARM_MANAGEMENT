<?php
// modules/animals/index.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';

Auth::start();
Auth::require();
RBAC::require('animals', 'view');

// Filters
$filterHealth = $_GET['health'] ?? '';
$filterFarm   = $_GET['farm_id'] ?? '';
$search       = $_GET['q'] ?? '';

$params     = [];
$conditions = [];

if (RBAC::farmScope()) { $conditions[] = "a.farm_id = ?"; $params[] = RBAC::farmScope(); }
if ($filterHealth)     { $conditions[] = "a.health_status = ?"; $params[] = $filterHealth; }
if ($filterFarm && !RBAC::farmScope()) { $conditions[] = "a.farm_id = ?"; $params[] = (int)$filterFarm; }
if ($search)           { $conditions[] = "(a.type LIKE ? OR a.breed LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$sqlWhere = $conditions ? 'WHERE a.sold = 0 AND ' . implode(' AND ', $conditions) : 'WHERE a.sold = 0';

$animals = DB::rows("
    SELECT a.*, f.name AS farm_name
    FROM animals a
    JOIN farms f ON f.id = a.farm_id
    $sqlWhere
    ORDER BY a.created_at DESC
", $params);

$farms = DB::rows("SELECT id, name FROM farms ORDER BY name");

// Summary counts
$total    = count($animals);
$ready    = count(array_filter($animals, fn($a) => days_left($a['birth_date'], $a['maturity_days']) <= 0));
$nearReady= count(array_filter($animals, fn($a) => ($d = days_left($a['birth_date'], $a['maturity_days'])) > 0 && $d <= 7));
$unhealthy= count(array_filter($animals, fn($a) => $a['health_status'] !== 'healthy'));

$pageTitle = 'Animals';
$activeNav = 'animals';
include __DIR__ . '/../../templates/header.php';
?>

<style>
  /* ── Page header ── */
  .animals-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 28px;
    padding-bottom: 22px;
    border-bottom: 1px solid var(--border, #e5e7eb);
  }
  .animals-header-left h2 {
    font-size: 1.65rem;
    font-weight: 800;
    letter-spacing: -0.4px;
    color: var(--text-heading, #111827);
    margin: 0 0 4px;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .animals-header-left h2 i {
    font-size: 1.4rem;
    color: #b45309;
    background: #fef3c7;
    width: 40px; height: 40px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .animals-header-left p {
    font-size: 0.875rem;
    color: var(--text-muted, #6b7280);
    margin: 0;
  }

  /* ── Summary cards ── */
  .animals-summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
  }
  .summary-card {
    background: var(--bg-card, #fff);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 12px;
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: box-shadow 0.2s;
  }
  .summary-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.07); }
  .summary-icon {
    width: 42px; height: 42px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
  }
  .summary-value {
    font-size: 1.4rem;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 3px;
    font-variant-numeric: tabular-nums;
    color: var(--text-heading, #111827);
  }
  .summary-label {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted, #9ca3af);
    font-weight: 600;
  }

  /* ── Filter bar ── */
  .animals-filters {
    background: var(--bg-card, #fff);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }
  .filter-search-wrap {
    position: relative;
    flex: 1;
    min-width: 220px;
  }
  .filter-search-wrap i {
    position: absolute;
    left: 12px; top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted, #9ca3af);
    font-size: 0.9rem;
    pointer-events: none;
  }
  .filter-search-wrap .search-clear {
    position: absolute;
    right: 10px; top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted, #9ca3af);
    cursor: pointer;
    font-size: 0.85rem;
    display: none;
    border: none;
    background: none;
    padding: 2px;
    line-height: 1;
  }
  .filter-search-wrap .search-clear.visible { display: block; }
  .filter-search-input {
    width: 100%;
    padding: 9px 36px 9px 36px;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 8px;
    font-size: 0.875rem;
    background: var(--bg-body, #f9fafb);
    color: var(--text-heading, #111827);
    transition: border-color 0.2s, box-shadow 0.2s;
    font-family: inherit;
  }
  .filter-search-input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.12);
    background: #fff;
  }
  .filter-select {
    padding: 9px 32px 9px 12px;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 8px;
    font-size: 0.875rem;
    background: var(--bg-body, #f9fafb);
    color: var(--text-heading, #374151);
    cursor: pointer;
    font-family: inherit;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%239ca3af' stroke-width='1.5' fill='none'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    transition: border-color 0.2s;
  }
  .filter-select:focus { outline: none; border-color: #667eea; }
  .filter-divider {
    width: 1px; height: 28px;
    background: var(--border, #e5e7eb);
    flex-shrink: 0;
  }
  .filter-results-count {
    font-size: 0.8rem;
    color: var(--text-muted, #9ca3af);
    white-space: nowrap;
    margin-left: auto;
  }
  .filter-results-count strong {
    color: var(--text-heading, #374151);
    font-weight: 700;
  }

  /* ── Table ── */
  .animals-table-card {
    background: var(--bg-card, #fff);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 12px;
    overflow: hidden;
  }
  .animals-table-card table {
    width: 100%;
    border-collapse: collapse;
  }
  .animals-table-card thead {
    background: var(--bg-body, #f9fafb);
    border-bottom: 1px solid var(--border, #e5e7eb);
  }
  .animals-table-card th {
    padding: 11px 16px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted, #6b7280);
    white-space: nowrap;
  }
  .animals-table-card th[data-sortable] { cursor: pointer; user-select: none; }
  .animals-table-card th[data-sortable]:hover { color: var(--text-heading, #374151); }
  .animals-table-card td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border, #f3f4f6);
    font-size: 0.875rem;
    vertical-align: middle;
  }
  .animals-table-card tr:last-child td { border-bottom: none; }
  .animals-table-card tbody tr {
    transition: background 0.15s;
  }
  .animals-table-card tbody tr:hover td {
    background: var(--bg-body, #f9fafb);
  }
  /* Hide-on-search class */
  .animals-table-card tbody tr.row-hidden { display: none; }

  .animal-type-cell strong {
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--text-heading, #111827);
  }
  .animal-type-cell small {
    display: block;
    font-size: 0.72rem;
    color: var(--text-muted, #9ca3af);
    margin-top: 2px;
  }

  /* Health badges */
  .health-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: capitalize;
  }
  .health-healthy    { background: #d1fae5; color: #065f46; }
  .health-sick       { background: #fee2e2; color: #991b1b; }
  .health-recovering { background: #fef3c7; color: #92400e; }
  .health-quarantined{ background: #ede9fe; color: #5b21b6; }

  /* Readiness */
  .readiness-wrap { min-width: 140px; }
  .readiness-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.72rem;
    margin-bottom: 5px;
  }
  .readiness-text { font-weight: 600; color: var(--text-heading, #374151); }
  .readiness-pct  { color: var(--text-muted, #9ca3af); font-variant-numeric: tabular-nums; }
  .readiness-bar-bg {
    height: 6px;
    background: var(--border, #e5e7eb);
    border-radius: 10px;
    overflow: hidden;
  }
  .readiness-bar-fill {
    height: 100%;
    border-radius: 10px;
    transition: width 0.6s ease;
  }
  .bar-ready   { background: #10b981; }
  .bar-near    { background: #f59e0b; }
  .bar-pending { background: #667eea; }

  /* Action buttons */
  .action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px; height: 30px;
    border-radius: 7px;
    border: 1px solid transparent;
    cursor: pointer;
    font-size: 0.82rem;
    transition: all 0.2s;
    text-decoration: none;
    background: none;
  }
  .action-btn-edit   { background: var(--bg-body,#f3f4f6); border-color: var(--border,#e5e7eb); color: #374151; }
  .action-btn-edit:hover   { background: #e5e7eb; }
  .action-btn-sell   { background: #d1fae5; border-color: #a7f3d0; color: #065f46; }
  .action-btn-sell:hover   { background: #a7f3d0; }
  .action-btn-delete { background: #fff1f2; border-color: #fecdd3; color: #e11d48; }
  .action-btn-delete:hover { background: #ffe4e6; }

  /* Empty state */
  .animals-empty {
    text-align: center;
    padding: 64px 20px;
    color: var(--text-muted, #9ca3af);
  }
  .animals-empty .empty-icon {
    width: 64px; height: 64px;
    background: var(--bg-body, #f3f4f6);
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem;
    margin: 0 auto 16px;
    color: #d1d5db;
  }
  .animals-empty p { font-size: 0.9rem; margin-bottom: 16px; }

  /* Responsive */
  @media (max-width: 900px) {
    .animals-summary { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 600px) {
    .animals-summary { grid-template-columns: 1fr 1fr; }
    .animals-header  { flex-direction: column; align-items: flex-start; gap: 12px; }
  }
</style>

<!-- Header -->
<div class="animals-header">
  <div class="animals-header-left">
    <h2><i class="bi bi-clipboard2-heart-fill"></i> Animals</h2>
    <p>Livestock inventory and maturity readiness tracker</p>
  </div>
  <?php if (RBAC::can('animals', 'create')): ?>
  <a href="create.php" class="ff-btn ff-btn-primary">
    <i class="bi bi-plus-circle"></i> Add Animal
  </a>
  <?php endif; ?>
</div>

<!-- Summary cards -->
<div class="animals-summary">
  <div class="summary-card">
    <div class="summary-icon" style="background:#eff6ff;color:#2563eb;">
      <i class="bi bi-clipboard2-data"></i>
    </div>
    <div>
      <div class="summary-value"><?= number_format($total) ?></div>
      <div class="summary-label">Total Animals</div>
    </div>
  </div>
  <div class="summary-card">
    <div class="summary-icon" style="background:#d1fae5;color:#059669;">
      <i class="bi bi-check-circle"></i>
    </div>
    <div>
      <div class="summary-value" style="color:#059669;"><?= number_format($ready) ?></div>
      <div class="summary-label">Ready to Sell</div>
    </div>
  </div>
  <div class="summary-card">
    <div class="summary-icon" style="background:#fef3c7;color:#d97706;">
      <i class="bi bi-hourglass-split"></i>
    </div>
    <div>
      <div class="summary-value" style="color:#d97706;"><?= number_format($nearReady) ?></div>
      <div class="summary-label">Ready Within 7d</div>
    </div>
  </div>
  <div class="summary-card">
    <div class="summary-icon" style="background:#fee2e2;color:#dc2626;">
      <i class="bi bi-heart-pulse"></i>
    </div>
    <div>
      <div class="summary-value" style="color:#dc2626;"><?= number_format($unhealthy) ?></div>
      <div class="summary-label">Health Issues</div>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="animals-filters">
  <div class="filter-search-wrap">
    <i class="bi bi-search"></i>
    <input
      type="text"
      id="liveSearch"
      class="filter-search-input"
      placeholder="Search type, breed, farm…"
      value="<?= e($search) ?>"
      autocomplete="off"
    >
    <button class="search-clear" id="searchClear" title="Clear search">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <div class="filter-divider"></div>

  <?php if (!RBAC::farmScope()): ?>
  <select id="filterFarm" class="filter-select" onchange="applyFilters()">
    <option value="">All Farms</option>
    <?php foreach ($farms as $f): ?>
    <option value="<?= e($f['id']) ?>" <?= $filterFarm == $f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>

  <select id="filterHealth" class="filter-select" onchange="applyFilters()">
    <option value="">All Health</option>
    <option value="healthy"     <?= $filterHealth === 'healthy'     ? 'selected' : '' ?>>Healthy</option>
    <option value="sick"        <?= $filterHealth === 'sick'        ? 'selected' : '' ?>>Sick</option>
    <option value="recovering"  <?= $filterHealth === 'recovering'  ? 'selected' : '' ?>>Recovering</option>
    <option value="quarantined" <?= $filterHealth === 'quarantined' ? 'selected' : '' ?>>Quarantined</option>
  </select>

  <div class="filter-divider"></div>

  <span class="filter-results-count">
    Showing <strong id="visibleCount"><?= $total ?></strong> of <?= $total ?> animals
  </span>

  <?php if ($search || $filterHealth || $filterFarm): ?>
  <a href="index.php" class="ff-btn ff-btn-outline ff-btn-sm">
    <i class="bi bi-x"></i> Clear filters
  </a>
  <?php endif; ?>
</div>

<!-- Table -->
<div class="animals-table-card">
  <div class="table-responsive">
    <table id="animalsTable">
      <thead>
        <tr>
          <th style="width:44px;">#</th>
          <th data-sortable>Type / Breed</th>
          <th data-sortable>Farm</th>
          <th data-sortable style="text-align:center;">Qty</th>
          <th data-sortable>Birth Date</th>
          <th data-sortable style="text-align:center;">Maturity</th>
          <th>Readiness</th>
          <th>Health</th>
          <th style="width:100px;">Actions</th>
        </tr>
      </thead>
      <tbody id="animalsBody">
        <?php foreach ($animals as $i => $a):
          $days = days_left($a['birth_date'], $a['maturity_days']);
          $pct  = readiness_pct($a['birth_date'], $a['maturity_days']);

          if ($days <= 0)      { $readyLabel = 'Ready to sell'; $barClass = 'bar-ready'; }
          elseif ($days <= 7)  { $readyLabel = $days . 'd left'; $barClass = 'bar-near'; }
          elseif ($days <= 14) { $readyLabel = ceil($days/7) . 'w left'; $barClass = 'bar-near'; }
          else                 { $readyLabel = ceil($days/7) . 'w left'; $barClass = 'bar-pending'; }

          $hClass = match($a['health_status']) {
            'healthy'     => 'health-healthy',
            'sick'        => 'health-sick',
            'recovering'  => 'health-recovering',
            'quarantined' => 'health-quarantined',
            default       => 'health-healthy'
          };
          $hIcon = match($a['health_status']) {
            'healthy'     => 'bi-check-circle-fill',
            'sick'        => 'bi-x-circle-fill',
            'recovering'  => 'bi-arrow-clockwise',
            'quarantined' => 'bi-shield-exclamation',
            default       => 'bi-check-circle-fill'
          };
        ?>
        <tr data-type="<?= strtolower(e($a['type'])) ?>"
            data-breed="<?= strtolower(e($a['breed'] ?? '')) ?>"
            data-farm="<?= strtolower(e($a['farm_name'])) ?>"
            data-farm-id="<?= $a['farm_id'] ?>"
            data-health="<?= e($a['health_status']) ?>">
          <td style="color:var(--text-muted,#9ca3af);font-size:0.78rem;font-variant-numeric:tabular-nums;"><?= $i + 1 ?></td>
          <td>
            <div class="animal-type-cell">
              <strong><?= e($a['type']) ?></strong>
              <small><?= $a['breed'] ? e($a['breed']) : '—' ?></small>
            </div>
          </td>
          <td style="font-size:0.85rem;"><?= e($a['farm_name']) ?></td>
          <td style="text-align:center;font-variant-numeric:tabular-nums;font-weight:600;"><?= number_format($a['quantity']) ?></td>
          <td style="font-size:0.85rem;white-space:nowrap;"><?= date('d M Y', strtotime($a['birth_date'])) ?></td>
          <td style="text-align:center;font-size:0.85rem;color:var(--text-muted,#6b7280);"><?= $a['maturity_days'] ?>d</td>
          <td>
            <div class="readiness-wrap">
              <div class="readiness-labels">
                <span class="readiness-text"><?= $readyLabel ?></span>
                <span class="readiness-pct"><?= $pct ?>%</span>
              </div>
              <div class="readiness-bar-bg">
                <div class="readiness-bar-fill <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
              </div>
            </div>
          </td>
          <td>
            <span class="health-badge <?= $hClass ?>">
              <i class="bi <?= $hIcon ?>"></i>
              <?= e($a['health_status']) ?>
            </span>
          </td>
          <td>
            <div style="display:flex;gap:6px;align-items:center;">
              <?php if (RBAC::can('animals', 'edit')): ?>
              <a href="edit.php?id=<?= $a['id'] ?>" class="action-btn action-btn-edit" title="Edit animal">
                <i class="bi bi-pencil"></i>
              </a>
              <?php endif; ?>
              <?php if (RBAC::can('animals', 'sell') && $days <= 0): ?>
              <a href="sell.php?id=<?= $a['id'] ?>" class="action-btn action-btn-sell" title="Record sale">
                <i class="bi bi-tag"></i>
              </a>
              <?php endif; ?>
              <?php if (RBAC::can('animals', 'delete')): ?>
              <form method="POST" action="delete.php" id="del-a-<?= $a['id'] ?>" style="display:inline;">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                <button type="button" class="action-btn action-btn-delete" title="Delete animal"
                  onclick="confirmDelete('del-a-<?= $a['id'] ?>')">
                  <i class="bi bi-trash3"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if (empty($animals)): ?>
    <div class="animals-empty">
      <div class="empty-icon"><i class="bi bi-clipboard2-x"></i></div>
      <p>No animals found matching your filters.</p>
      <?php if (RBAC::can('animals', 'create')): ?>
      <a href="create.php" class="ff-btn ff-btn-primary"><i class="bi bi-plus-circle"></i> Add your first animal</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  const searchInput  = document.getElementById('liveSearch');
  const searchClear  = document.getElementById('searchClear');
  const filterHealth = document.getElementById('filterHealth');
  const filterFarm   = document.getElementById('filterFarm');
  const tbody        = document.getElementById('animalsBody');
  const visibleCount = document.getElementById('visibleCount');

  function applyFilters() {
    const q      = (searchInput?.value ?? '').toLowerCase().trim();
    const health = filterHealth?.value ?? '';
    const farm   = filterFarm?.value   ?? '';

    let visible = 0;
    const rows = tbody.querySelectorAll('tr');

    rows.forEach(row => {
      const type    = row.dataset.type    ?? '';
      const breed   = row.dataset.breed   ?? '';
      const farmName= row.dataset.farm    ?? '';
      const farmId  = row.dataset.farmId  ?? '';
      const rowHealth = row.dataset.health ?? '';

      const matchSearch = !q || type.includes(q) || breed.includes(q) || farmName.includes(q);
      const matchHealth = !health || rowHealth === health;
      const matchFarm   = !farm   || farmId === farm;

      if (matchSearch && matchHealth && matchFarm) {
        row.classList.remove('row-hidden');
        visible++;
      } else {
        row.classList.add('row-hidden');
      }
    });

    if (visibleCount) visibleCount.textContent = visible;

    // Toggle clear button
    if (searchClear) {
      searchClear.classList.toggle('visible', q.length > 0);
    }
  }

  // Live search as user types
  if (searchInput) {
    searchInput.addEventListener('input', applyFilters);
  }

  // Clear button
  if (searchClear) {
    searchClear.addEventListener('click', () => {
      searchInput.value = '';
      searchInput.focus();
      applyFilters();
    });
  }

  // Dropdowns also trigger live filter
  window.applyFilters = applyFilters;

  // Init sortable table headers
  if (typeof TableSorter !== 'undefined') {
    TableSorter.init('animalsTable');
  }

  // Run once on load to sync with any server-side pre-filled values
  applyFilters();
})();
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>