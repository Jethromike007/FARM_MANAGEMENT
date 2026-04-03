<?php
// modules/eggs/index.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';

Auth::start();
Auth::require();
RBAC::require('eggs', 'view');

$scope = RBAC::farmScope();
$farmFilter = $_GET['farm_id'] ?? '';
$dateFrom   = $_GET['date_from'] ?? date('Y-m-01');
$dateTo     = $_GET['date_to']   ?? date('Y-m-d');

$conditions = ['ep.date_produced BETWEEN ? AND ?'];
$params     = [$dateFrom, $dateTo];

if ($scope) { $conditions[] = "ep.farm_id = ?"; $params[] = $scope; }
elseif ($farmFilter) { $conditions[] = "ep.farm_id = ?"; $params[] = (int)$farmFilter; }

$sqlWhere = 'WHERE ' . implode(' AND ', $conditions);

$records = DB::rows("
    SELECT ep.*, f.name AS farm_name, a.type AS animal_type, a.breed AS animal_breed, u.name AS recorded_by_name
    FROM egg_production ep
    JOIN farms f ON f.id = ep.farm_id
    LEFT JOIN animals a ON a.id = ep.animal_id
    LEFT JOIN users u ON u.id = ep.recorded_by
    $sqlWhere
    ORDER BY ep.date_produced DESC, ep.id DESC
", $params);

// Summary for the period
$summary = DB::row("
    SELECT 
        COALESCE(SUM(quantity),0) AS total_eggs,
        COALESCE(SUM(CASE WHEN sold=1 THEN price_sold ELSE 0 END),0) AS total_revenue,
        COUNT(*) AS entry_count,
        COALESCE(AVG(quantity),0) AS avg_daily
    FROM egg_production ep $sqlWhere
", $params);

// Today's total
$todayTotal = DB::row("SELECT COALESCE(SUM(quantity),0) AS total FROM egg_production WHERE date_produced = CURDATE()" . ($scope ? " AND farm_id=$scope" : ""))['total'];

$farms = DB::rows("SELECT id, name FROM farms ORDER BY name");

$pageTitle = 'Egg Production';
$activeNav = 'eggs';
include __DIR__ . '/../../templates/header.php';
?>

<div class="ff-page-header">
  <div>
    <h2 class="ff-page-title">🥚 Egg Production</h2>
    <p class="ff-page-subtitle">Daily egg tracking and analysis</p>
  </div>
  <?php if (RBAC::can('eggs', 'create')): ?>
  <a href="create.php" class="ff-btn ff-btn-primary"><i class="bi bi-plus-circle"></i> Record Eggs</a>
  <?php endif; ?>
</div>

<!-- Summary Cards -->
<div class="grid-4 mb-24">
  <div class="stat-card teal">
    <div class="stat-icon teal">🥚</div>
    <div class="stat-value"><?= number_format($todayTotal) ?></div>
    <div class="stat-label">Eggs Today</div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon blue">📦</div>
    <div class="stat-value"><?= number_format($summary['total_eggs']) ?></div>
    <div class="stat-label">Period Total</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon green">📊</div>
    <div class="stat-value"><?= number_format($summary['avg_daily'], 0) ?></div>
    <div class="stat-label">Daily Average</div>
  </div>
  <div class="stat-card purple">
    <div class="stat-icon purple">💰</div>
    <div class="stat-value" style="font-size:18px;"><?= money($summary['total_revenue']) ?></div>
    <div class="stat-label">Period Revenue</div>
  </div>
</div>

<!-- Filters -->
<div class="ff-card mb-24">
  <form method="GET" class="ff-search-bar" style="flex-wrap:wrap;gap:10px;">
    <?php if (!$scope): ?>
    <select name="farm_id" class="ff-form-control" style="width:auto;padding:8px 12px;">
      <option value="">All Farms</option>
      <?php foreach ($farms as $f): ?>
      <option value="<?= $f['id'] ?>" <?= $farmFilter == $f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <div style="display:flex;align-items:center;gap:8px;">
      <label class="ff-form-label" style="margin:0;white-space:nowrap;">From:</label>
      <input type="date" name="date_from" class="ff-form-control" value="<?= e($dateFrom) ?>" style="width:auto;">
    </div>
    <div style="display:flex;align-items:center;gap:8px;">
      <label class="ff-form-label" style="margin:0;white-space:nowrap;">To:</label>
      <input type="date" name="date_to" class="ff-form-control" value="<?= e($dateTo) ?>" style="width:auto;">
    </div>
    <button type="submit" class="ff-btn ff-btn-primary ff-btn-sm"><i class="bi bi-funnel"></i> Filter</button>
    <a href="index.php" class="ff-btn ff-btn-outline ff-btn-sm">Reset</a>
    <a href="<?= APP_URL ?>/modules/accounting/export.php?type=eggs&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>" 
       class="ff-btn ff-btn-outline ff-btn-sm" style="margin-left:auto;">
      <i class="bi bi-download"></i> Export CSV
    </a>
  </form>
</div>

<!-- Records Table -->
<div class="ff-card">
  <div class="table-responsive">
    <table class="ff-table" id="eggsTable">
      <thead>
        <tr>
          <th data-sortable>Date</th>
          <th>Farm</th>
          <th>Flock</th>
          <th data-sortable>Qty Produced</th>
          <th>Target</th>
          <th>Status</th>
          <th>Sold</th>
          <th>Revenue</th>
          <th>Notes</th>
          <th>Recorded By</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($records as $r):
          $cls = egg_status_class($r['quantity'], $r['daily_target']);
          $pct = $r['daily_target'] ? min(100, round(($r['quantity'] / $r['daily_target']) * 100)) : null;
        ?>
        <tr>
          <td class="text-mono" style="white-space:nowrap;"><?= date('d M Y', strtotime($r['date_produced'])) ?></td>
          <td><?= e($r['farm_name']) ?></td>
          <td style="font-size:12px;">
            <?php if ($r['animal_type']): ?>
              <?= e($r['animal_type']) ?><?= $r['animal_breed'] ? ' / ' . e($r['animal_breed']) : '' ?>
            <?php else: ?><span style="color:var(--text-muted);">—</span><?php endif; ?>
          </td>
          <td class="text-mono" style="font-weight:700;"><?= number_format($r['quantity']) ?></td>
          <td class="text-mono" style="color:var(--text-muted);"><?= $r['daily_target'] ? number_format($r['daily_target']) : '—' ?></td>
          <td>
            <?php if ($pct !== null): ?>
            <div class="ff-progress-wrap" style="min-width:100px;">
              <div class="ff-progress-label">
                <span class="ff-badge ff-badge-<?= $cls ?>" style="font-size:10px;"><?= $pct ?>%</span>
              </div>
              <div class="ff-progress" style="margin-top:4px;">
                <div class="ff-progress-bar <?= $cls ?>" style="width:<?= $pct ?>%"></div>
              </div>
            </div>
            <?php else: ?><span style="color:var(--text-muted);">No target</span><?php endif; ?>
          </td>
          <td>
            <?php if ($r['sold']): ?>
            <span class="ff-badge ff-badge-success"><i class="bi bi-check-circle"></i> Sold</span>
            <?php else: ?>
            <span class="ff-badge ff-badge-secondary">Unsold</span>
            <?php endif; ?>
          </td>
          <td class="text-mono"><?= $r['price_sold'] ? money($r['price_sold']) : '—' ?></td>
          <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:var(--text-muted);">
            <?= $r['notes'] ? e($r['notes']) : '—' ?>
          </td>
          <td style="font-size:12px;"><?= e($r['recorded_by_name'] ?? 'System') ?></td>
          <td>
            <div class="d-flex gap-8">
              <?php if (RBAC::can('eggs', 'edit')): ?>
              <a href="edit.php?id=<?= $r['id'] ?>" class="ff-btn ff-btn-outline ff-btn-sm"><i class="bi bi-pencil"></i></a>
              <?php endif; ?>
              <?php if (RBAC::can('eggs', 'delete')): ?>
              <form method="POST" action="delete.php" id="del-e-<?= $r['id'] ?>" style="display:inline;">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button type="button" class="ff-btn ff-btn-danger ff-btn-sm" onclick="confirmDelete('del-e-<?= $r['id'] ?>')">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($records)): ?>
        <tr><td colspan="11" style="text-align:center;padding:40px;color:var(--text-muted);">No egg records found for this period.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>document.addEventListener('DOMContentLoaded', () => TableSorter.init('eggsTable'));</script>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
