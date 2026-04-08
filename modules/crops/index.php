<?php
// modules/crops/index.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';

Auth::start();
Auth::require();
RBAC::require('crops', 'view');

$search       = $_GET['q'] ?? '';
$farmFilter   = $_GET['farm_id'] ?? '';

$conditions = ['c.harvested = 0'];
$params = [];

if (RBAC::farmScope())                  { $conditions[] = "c.farm_id = ?"; $params[] = RBAC::farmScope(); }
if ($farmFilter && !RBAC::farmScope())  { $conditions[] = "c.farm_id = ?"; $params[] = (int)$farmFilter; }
if ($search)                            { $conditions[] = "(c.type LIKE ? OR c.variety LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$sqlWhere = 'WHERE ' . implode(' AND ', $conditions);

$crops = DB::rows("
    SELECT c.*, f.name AS farm_name
    FROM crops c JOIN farms f ON f.id = c.farm_id
    $sqlWhere
    ORDER BY c.created_at DESC
", $params);

$farms = DB::rows("SELECT id, name FROM farms ORDER BY name");

// Summary counts
$total     = count($crops);
$ready     = count(array_filter($crops, fn($c) => days_left($c['planting_date'], $c['maturity_days']) <= 0));
$nearReady = count(array_filter($crops, fn($c) => ($d = days_left($c['planting_date'], $c['maturity_days'])) > 0 && $d <= 14));
$pending   = $total - $ready - $nearReady;

$pageTitle = 'Crops';
$activeNav = 'crops';
include __DIR__ . '/../../templates/header.php';
?>

<style>
  /* ── Header ── */
  .crops-header {
    display: flex; align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 28px; padding-bottom: 22px;
    border-bottom: 1px solid var(--border, #e5e7eb);
  }
  .crops-header-left h2 {
    font-size: 1.65rem; font-weight: 800; letter-spacing: -0.4px;
    color: var(--text-heading, #111827); margin: 0 0 4px;
    display: flex; align-items: center; gap: 10px;
  }
  .crops-header-left h2 i {
    font-size: 1.3rem; color: #16a34a; background: #dcfce7;
    width: 40px; height: 40px; border-radius: 10px;
    display: inline-flex; align-items: center; justify-content: center;
  }
  .crops-header-left p { font-size: 0.875rem; color: var(--text-muted, #6b7280); margin: 0; }

  /* ── Summary cards ── */
  .crops-summary {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 16px; margin-bottom: 24px;
  }
  .summary-card {
    background: var(--bg-card, #fff); border: 1px solid var(--border, #e5e7eb);
    border-radius: 12px; padding: 16px 18px;
    display: flex; align-items: center; gap: 14px;
    transition: box-shadow 0.2s;
  }
  .summary-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.07); }
  .summary-icon {
    width: 42px; height: 42px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem; flex-shrink: 0;
  }
  .summary-value {
    font-size: 1.4rem; font-weight: 800; line-height: 1;
    margin-bottom: 3px; font-variant-numeric: tabular-nums;
    color: var(--text-heading, #111827);
  }
  .summary-label {
    font-size: 0.72rem; text-transform: uppercase;
    letter-spacing: 0.5px; color: var(--text-muted, #9ca3af); font-weight: 600;
  }

  /* ── Filter bar ── */
  .crops-filters {
    background: var(--bg-card, #fff); border: 1px solid var(--border, #e5e7eb);
    border-radius: 12px; padding: 16px 20px; margin-bottom: 20px;
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
  }
  .filter-search-wrap {
    position: relative; flex: 1; min-width: 220px;
  }
  .filter-search-wrap > i {
    position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    color: var(--text-muted, #9ca3af); font-size: 0.9rem; pointer-events: none;
  }
  .search-clear {
    position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
    color: var(--text-muted, #9ca3af); cursor: pointer; font-size: 0.85rem;
    display: none; border: none; background: none; padding: 2px; line-height: 1;
  }
  .search-clear.visible { display: block; }
  .filter-search-input {
    width: 100%; padding: 9px 36px;
    border: 1px solid var(--border, #e5e7eb); border-radius: 8px;
    font-size: 0.875rem; background: var(--bg-body, #f9fafb);
    color: var(--text-heading, #111827); transition: border-color 0.2s, box-shadow 0.2s;
    font-family: inherit;
  }
  .filter-search-input:focus {
    outline: none; border-color: #16a34a;
    box-shadow: 0 0 0 3px rgba(22,163,74,0.1); background: #fff;
  }
  .filter-select {
    padding: 9px 32px 9px 12px; border: 1px solid var(--border, #e5e7eb);
    border-radius: 8px; font-size: 0.875rem; background: var(--bg-body, #f9fafb);
    color: var(--text-heading, #374151); cursor: pointer; font-family: inherit;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%239ca3af' stroke-width='1.5' fill='none'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 10px center;
    transition: border-color 0.2s;
  }
  .filter-select:focus { outline: none; border-color: #16a34a; }
  .filter-divider { width: 1px; height: 28px; background: var(--border, #e5e7eb); flex-shrink: 0; }
  .filter-results-count {
    font-size: 0.8rem; color: var(--text-muted, #9ca3af);
    white-space: nowrap; margin-left: auto;
  }
  .filter-results-count strong { color: var(--text-heading, #374151); font-weight: 700; }

  /* ── Table ── */
  .crops-table-card {
    background: var(--bg-card, #fff); border: 1px solid var(--border, #e5e7eb);
    border-radius: 12px; overflow: hidden;
  }
  .crops-table-card table { width: 100%; border-collapse: collapse; }
  .crops-table-card thead {
    background: var(--bg-body, #f9fafb);
    border-bottom: 1px solid var(--border, #e5e7eb);
  }
  .crops-table-card th {
    padding: 11px 16px; font-size: 0.72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px;
    color: var(--text-muted, #6b7280); white-space: nowrap;
  }
  .crops-table-card th[data-sortable] { cursor: pointer; user-select: none; }
  .crops-table-card th[data-sortable]:hover { color: var(--text-heading, #374151); }
  .crops-table-card td {
    padding: 14px 16px; border-bottom: 1px solid var(--border, #f3f4f6);
    font-size: 0.875rem; vertical-align: middle;
  }
  .crops-table-card tr:last-child td { border-bottom: none; }
  .crops-table-card tbody tr { transition: background 0.15s; }
  .crops-table-card tbody tr:hover td { background: var(--bg-body, #f9fafb); }
  .crops-table-card tbody tr.row-hidden { display: none; }

  .crop-type-cell strong { font-weight: 600; font-size: 0.875rem; color: var(--text-heading, #111827); }
  .crop-type-cell small  { display: block; font-size: 0.72rem; color: var(--text-muted, #9ca3af); margin-top: 2px; }

  /* Readiness */
  .readiness-wrap { min-width: 150px; }
  .readiness-labels {
    display: flex; justify-content: space-between;
    font-size: 0.72rem; margin-bottom: 5px;
  }
  .readiness-text { font-weight: 600; color: var(--text-heading, #374151); }
  .readiness-pct  { color: var(--text-muted, #9ca3af); font-variant-numeric: tabular-nums; }
  .readiness-bar-bg { height: 6px; background: var(--border, #e5e7eb); border-radius: 10px; overflow: hidden; }
  .readiness-bar-fill { height: 100%; border-radius: 10px; transition: width 0.6s ease; }
  .bar-ready   { background: #10b981; }
  .bar-near    { background: #f59e0b; }
  .bar-pending { background: #667eea; }

  /* Action buttons */
  .action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 7px; border: 1px solid transparent;
    cursor: pointer; font-size: 0.82rem; transition: all 0.2s;
    text-decoration: none; background: none;
  }
  .action-btn-icon {
    width: 30px; height: 30px;
  }
  .action-btn-harvest {
    padding: 5px 10px; gap: 5px; font-size: 0.78rem; font-weight: 600;
    background: #d1fae5; border-color: #a7f3d0; color: #065f46; height: 30px;
  }
  .action-btn-harvest:hover { background: #a7f3d0; }
  .action-btn-edit   { background: var(--bg-body,#f3f4f6); border-color: var(--border,#e5e7eb); color: #374151; }
  .action-btn-edit:hover   { background: #e5e7eb; }
  .action-btn-delete { background: #fff1f2; border-color: #fecdd3; color: #e11d48; }
  .action-btn-delete:hover { background: #ffe4e6; }

  /* Empty */
  .crops-empty { text-align: center; padding: 64px 20px; color: var(--text-muted, #9ca3af); }
  .crops-empty .empty-icon {
    width: 64px; height: 64px; background: var(--bg-body, #f3f4f6);
    border-radius: 16px; display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem; margin: 0 auto 16px; color: #d1d5db;
  }
  .crops-empty p { font-size: 0.9rem; margin-bottom: 16px; }

  @media (max-width: 900px) { .crops-summary { grid-template-columns: repeat(2,1fr); } }
  @media (max-width: 600px) {
    .crops-header { flex-direction: column; align-items: flex-start; gap: 12px; }
    .crops-summary { grid-template-columns: 1fr 1fr; }
  }
</style>

<!-- Header -->
<div class="crops-header">
  <div class="crops-header-left">
    <h2><i class="bi bi-flower2"></i> Crops</h2>
    <p>Planting schedule and harvest readiness tracker</p>
  </div>
  <?php if (RBAC::can('crops', 'create')): ?>
  <a href="create.php" class="ff-btn ff-btn-primary">
    <i class="bi bi-plus-circle"></i> Add Crop
  </a>
  <?php endif; ?>
</div>

<!-- Summary cards -->
<div class="crops-summary">
  <div class="summary-card">
    <div class="summary-icon" style="background:#f0fdf4;color:#16a34a;">
      <i class="bi bi-grid-3x3-gap"></i>
    </div>
    <div>
      <div class="summary-value"><?= number_format($total) ?></div>
      <div class="summary-label">Total Crops</div>
    </div>
  </div>
  <div class="summary-card">
    <div class="summary-icon" style="background:#d1fae5;color:#059669;">
      <i class="bi bi-scissors"></i>
    </div>
    <div>
      <div class="summary-value" style="color:#059669;"><?= number_format($ready) ?></div>
      <div class="summary-label">Ready to Harvest</div>
    </div>
  </div>
  <div class="summary-card">
    <div class="summary-icon" style="background:#fef3c7;color:#d97706;">
      <i class="bi bi-hourglass-split"></i>
    </div>
    <div>
      <div class="summary-value" style="color:#d97706;"><?= number_format($nearReady) ?></div>
      <div class="summary-label">Ready Within 14d</div>
    </div>
  </div>
  <div class="summary-card">
    <div class="summary-icon" style="background:#eff6ff;color:#2563eb;">
      <i class="bi bi-clock-history"></i>
    </div>
    <div>
      <div class="summary-value"><?= number_format($pending) ?></div>
      <div class="summary-label">Still Growing</div>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="crops-filters">
  <div class="filter-search-wrap">
    <i class="bi bi-search"></i>
    <input
      type="text"
      id="liveSearch"
      class="filter-search-input"
      placeholder="Search by crop type, variety, farm…"
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
    <option value="<?= $f['id'] ?>" <?= $farmFilter == $f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>

  <select id="filterReady" class="filter-select" onchange="applyFilters()">
    <option value="">All Statuses</option>
    <option value="ready">Ready to Harvest</option>
    <option value="near">Within 14 days</option>
    <option value="pending">Still Growing</option>
  </select>

  <div class="filter-divider"></div>

  <span class="filter-results-count">
    Showing <strong id="visibleCount"><?= $total ?></strong> of <?= $total ?> crops
  </span>

  <?php if ($search || $farmFilter): ?>
  <a href="index.php" class="ff-btn ff-btn-outline ff-btn-sm">
    <i class="bi bi-x"></i> Clear
  </a>
  <?php endif; ?>
</div>

<!-- Table -->
<div class="crops-table-card">
  <div class="table-responsive">
    <table id="cropsTable">
      <thead>
        <tr>
          <th style="width:44px;">#</th>
          <th data-sortable>Type / Variety</th>
          <th data-sortable>Farm</th>
          <th data-sortable style="text-align:center;">Quantity</th>
          <th data-sortable>Planted</th>
          <th data-sortable style="text-align:center;">Maturity</th>
          <th>Readiness</th>
          <th style="width:130px;">Actions</th>
        </tr>
      </thead>
      <tbody id="cropsBody">
        <?php foreach ($crops as $i => $c):
          $days = days_left($c['planting_date'], $c['maturity_days']);
          $pct  = readiness_pct($c['planting_date'], $c['maturity_days']);

          if ($days <= 0)       { $readyLabel = 'Ready to harvest'; $barClass = 'bar-ready'; $readyStatus = 'ready'; }
          elseif ($days <= 14)  { $readyLabel = $days . 'd left';   $barClass = 'bar-near';  $readyStatus = 'near'; }
          else                  { $readyLabel = ceil($days/7) . 'w left'; $barClass = 'bar-pending'; $readyStatus = 'pending'; }
        ?>
        <tr data-type="<?= strtolower(e($c['type'])) ?>"
            data-variety="<?= strtolower(e($c['variety'] ?? '')) ?>"
            data-farm="<?= strtolower(e($c['farm_name'])) ?>"
            data-farm-id="<?= $c['farm_id'] ?>"
            data-ready-status="<?= $readyStatus ?>">
          <td style="color:var(--text-muted,#9ca3af);font-size:0.78rem;font-variant-numeric:tabular-nums;"><?= $i + 1 ?></td>
          <td>
            <div class="crop-type-cell">
              <strong><?= e($c['type']) ?></strong>
              <small><?= $c['variety'] ? e($c['variety']) : '—' ?></small>
            </div>
          </td>
          <td style="font-size:0.85rem;"><?= e($c['farm_name']) ?></td>
          <td style="text-align:center;font-size:0.85rem;font-variant-numeric:tabular-nums;font-weight:600;">
            <?= e($c['quantity']) ?> <span style="color:var(--text-muted,#9ca3af);font-weight:400;"><?= e($c['quantity_unit']) ?></span>
          </td>
          <td style="font-size:0.85rem;white-space:nowrap;"><?= date('d M Y', strtotime($c['planting_date'])) ?></td>
          <td style="text-align:center;font-size:0.85rem;color:var(--text-muted,#6b7280);"><?= $c['maturity_days'] ?>d</td>
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
            <div style="display:flex;gap:6px;align-items:center;">
              <?php if (RBAC::can('crops', 'edit')): ?>
              <a href="edit.php?id=<?= $c['id'] ?>" class="action-btn action-btn-icon action-btn-edit" title="Edit crop">
                <i class="bi bi-pencil"></i>
              </a>
              <?php endif; ?>
              <?php if (RBAC::can('crops', 'harvest') && $days <= 0): ?>
              <a href="harvest.php?id=<?= $c['id'] ?>" class="action-btn action-btn-harvest" title="Record harvest">
                <i class="bi bi-scissors"></i> Harvest
              </a>
              <?php endif; ?>
              <?php if (RBAC::can('crops', 'delete')): ?>
              <form method="POST" action="delete.php" id="del-c-<?= $c['id'] ?>" style="display:inline;">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button type="button" class="action-btn action-btn-icon action-btn-delete" title="Delete crop"
                  onclick="confirmDelete('del-c-<?= $c['id'] ?>')">
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

    <?php if (empty($crops)): ?>
    <div class="crops-empty">
      <div class="empty-icon"><i class="bi bi-flower1"></i></div>
      <p>No crops found matching your filters.</p>
      <?php if (RBAC::can('crops', 'create')): ?>
      <a href="create.php" class="ff-btn ff-btn-primary"><i class="bi bi-plus-circle"></i> Plant your first crop</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  const searchInput  = document.getElementById('liveSearch');
  const searchClear  = document.getElementById('searchClear');
  const filterFarm   = document.getElementById('filterFarm');
  const filterReady  = document.getElementById('filterReady');
  const tbody        = document.getElementById('cropsBody');
  const visibleCount = document.getElementById('visibleCount');

  function applyFilters() {
    const q     = (searchInput?.value ?? '').toLowerCase().trim();
    const farm  = filterFarm?.value  ?? '';
    const ready = filterReady?.value ?? '';

    let visible = 0;
    tbody.querySelectorAll('tr').forEach(row => {
      const type    = row.dataset.type    ?? '';
      const variety = row.dataset.variety ?? '';
      const farmName= row.dataset.farm    ?? '';
      const farmId  = row.dataset.farmId  ?? '';
      const status  = row.dataset.readyStatus ?? '';

      const matchSearch = !q     || type.includes(q) || variety.includes(q) || farmName.includes(q);
      const matchFarm   = !farm  || farmId === farm;
      const matchReady  = !ready || status === ready;

      if (matchSearch && matchFarm && matchReady) {
        row.classList.remove('row-hidden'); visible++;
      } else {
        row.classList.add('row-hidden');
      }
    });

    if (visibleCount) visibleCount.textContent = visible;
    if (searchClear)  searchClear.classList.toggle('visible', q.length > 0);
  }

  if (searchInput) searchInput.addEventListener('input', applyFilters);

  if (searchClear) {
    searchClear.addEventListener('click', () => {
      searchInput.value = '';
      searchInput.focus();
      applyFilters();
    });
  }

  window.applyFilters = applyFilters;

  if (typeof TableSorter !== 'undefined') TableSorter.init('cropsTable');

  applyFilters();
})();
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>