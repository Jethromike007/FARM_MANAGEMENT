<?php
// modules/crops/index.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';

Auth::start();
Auth::require();
RBAC::require('crops', 'view');

$search   = $_GET['q'] ?? '';
$farmFilter = $_GET['farm_id'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$conditions = ['c.harvested = 0'];
$params = [];

if (RBAC::farmScope()) { $conditions[] = "c.farm_id = ?"; $params[] = RBAC::farmScope(); }
if ($farmFilter && !RBAC::farmScope()) { $conditions[] = "c.farm_id = ?"; $params[] = (int)$farmFilter; }
if ($search) { $conditions[] = "(c.type LIKE ? OR c.variety LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$sqlWhere = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$crops = DB::rows("
    SELECT c.*, f.name AS farm_name
    FROM crops c JOIN farms f ON f.id = c.farm_id
    $sqlWhere
    ORDER BY c.created_at DESC
", $params);

$farms = DB::rows("SELECT id, name FROM farms ORDER BY name");

$pageTitle = 'Crops';
$activeNav = 'crops';
include __DIR__ . '/../../templates/header.php';
?>

<div class="ff-page-header">
  <div>
    <h2 class="ff-page-title">🌽 Crops</h2>
    <p class="ff-page-subtitle">Planting schedule and harvest tracker</p>
  </div>
  <?php if (RBAC::can('crops', 'create')): ?>
  <a href="create.php" class="ff-btn ff-btn-primary"><i class="bi bi-plus-circle"></i> Add Crop</a>
  <?php endif; ?>
</div>

<!-- Filters -->
<div class="ff-card mb-24">
  <form method="GET" class="ff-search-bar">
    <div class="ff-search-wrap">
      <i class="bi bi-search search-icon"></i>
      <input class="ff-search-input w-100" type="text" name="q" placeholder="Search crops…" value="<?= e($search) ?>">
    </div>
    <?php if (!RBAC::farmScope()): ?>
    <select name="farm_id" class="ff-form-control" style="width:auto;padding:8px 12px;">
      <option value="">All Farms</option>
      <?php foreach ($farms as $f): ?>
      <option value="<?= $f['id'] ?>" <?= $farmFilter == $f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <button type="submit" class="ff-btn ff-btn-primary ff-btn-sm"><i class="bi bi-funnel"></i> Filter</button>
    <a href="index.php" class="ff-btn ff-btn-outline ff-btn-sm">Clear</a>
  </form>
</div>

<div class="ff-card">
  <div class="table-responsive">
    <table class="ff-table" id="cropsTable">
      <thead>
        <tr>
          <th>#</th>
          <th data-sortable>Type / Variety</th>
          <th data-sortable>Farm</th>
          <th data-sortable>Quantity</th>
          <th data-sortable>Planted</th>
          <th>Readiness</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($crops as $i => $c):
          $days = days_left($c['planting_date'], $c['maturity_days']);
          $pct  = readiness_pct($c['planting_date'], $c['maturity_days']);
          $cls  = readiness_class($days);
          $label = $days <= 0 ? '✅ Ready' : ($days <= 14 ? "⚡ {$days}d" : ceil($days/7) . "w left");
        ?>
        <tr>
          <td class="text-mono" style="color:var(--text-muted);"><?= $i+1 ?></td>
          <td>
            <div style="font-weight:600;"><?= e($c['type']) ?></div>
            <div style="font-size:11px;color:var(--text-muted);"><?= $c['variety'] ? e($c['variety']) : '—' ?></div>
          </td>
          <td><?= e($c['farm_name']) ?></td>
          <td class="text-mono"><?= e($c['quantity']) ?> <?= e($c['quantity_unit']) ?></td>
          <td><?= date('d M Y', strtotime($c['planting_date'])) ?></td>
          <td style="min-width:160px;">
            <div class="ff-progress-wrap">
              <div class="ff-progress-label">
                <span><?= $label ?></span>
                <span class="text-mono"><?= $pct ?>%</span>
              </div>
              <div class="ff-progress">
                <div class="ff-progress-bar <?= $cls ?>" style="width:<?= $pct ?>%"></div>
              </div>
            </div>
          </td>
          <td>
            <div class="d-flex gap-8">
              <?php if (RBAC::can('crops', 'edit')): ?>
              <a href="edit.php?id=<?= $c['id'] ?>" class="ff-btn ff-btn-outline ff-btn-sm"><i class="bi bi-pencil"></i></a>
              <?php endif; ?>
              <?php if (RBAC::can('crops', 'harvest') && $days <= 0): ?>
              <a href="harvest.php?id=<?= $c['id'] ?>" class="ff-btn ff-btn-success ff-btn-sm"><i class="bi bi-scissors"></i> Harvest</a>
              <?php endif; ?>
              <?php if (RBAC::can('crops', 'delete')): ?>
              <form method="POST" action="delete.php" id="del-c-<?= $c['id'] ?>" style="display:inline;">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button type="button" class="ff-btn ff-btn-danger ff-btn-sm" onclick="confirmDelete('del-c-<?= $c['id'] ?>')">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($crops)): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted);">No crops found. <a href="create.php">Plant something?</a></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>document.addEventListener('DOMContentLoaded', () => TableSorter.init('cropsTable'));</script>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
