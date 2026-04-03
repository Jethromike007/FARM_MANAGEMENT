<?php
// modules/farms/index.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';

Auth::start();
Auth::require();
RBAC::require('farms', 'view');

$scope = RBAC::farmScope();
$where = $scope ? "WHERE id = $scope" : '';
$farms = DB::rows("SELECT f.*, 
    (SELECT COUNT(*) FROM animals a WHERE a.farm_id = f.id AND a.sold = 0) AS animal_count,
    (SELECT COUNT(*) FROM crops c WHERE c.farm_id = f.id AND c.harvested = 0) AS crop_count,
    (SELECT COALESCE(SUM(total_amount),0) FROM sales s WHERE s.farm_id = f.id) AS total_revenue
    FROM farms f $where ORDER BY f.created_at DESC");

$pageTitle = 'Farms';
$activeNav = 'farms';
include __DIR__ . '/../../templates/header.php';
?>

<div class="ff-page-header">
  <div>
    <h2 class="ff-page-title">🏡 Farms</h2>
    <p class="ff-page-subtitle">All registered farm locations</p>
  </div>
  <?php if (RBAC::can('farms', 'create')): ?>
  <a href="create.php" class="ff-btn ff-btn-primary"><i class="bi bi-plus-circle"></i> Add Farm</a>
  <?php endif; ?>
</div>

<div class="grid-3">
  <?php foreach ($farms as $farm):
    $typeIcons = ['crop'=>'🌿','livestock'=>'🐄','mixed'=>'🌾','poultry'=>'🐔','aquaculture'=>'🐟','orchard'=>'🍎'];
    $icon = $typeIcons[$farm['type']] ?? '🌾';
  ?>
  <div class="ff-card">
    <div class="ff-card-body">
      <div class="d-flex align-center gap-12 mb-16">
        <div style="font-size:32px;"><?= $icon ?></div>
        <div>
          <div style="font-size:15px;font-weight:700;"><?= e($farm['name']) ?></div>
          <div style="font-size:12px;color:var(--text-muted);"><?= e($farm['city']) ?>, <?= e($farm['state']) ?></div>
        </div>
        <span class="ff-badge ff-badge-info" style="margin-left:auto;"><?= e(ucfirst($farm['type'])) ?></span>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
        <div style="background:var(--bg-body);border-radius:8px;padding:10px;text-align:center;">
          <div style="font-size:20px;font-weight:700;font-family:var(--ff-mono);"><?= $farm['animal_count'] ?></div>
          <div style="font-size:11px;color:var(--text-muted);">Animals</div>
        </div>
        <div style="background:var(--bg-body);border-radius:8px;padding:10px;text-align:center;">
          <div style="font-size:20px;font-weight:700;font-family:var(--ff-mono);"><?= $farm['crop_count'] ?></div>
          <div style="font-size:11px;color:var(--text-muted);">Crops</div>
        </div>
      </div>

      <div style="margin-bottom:14px;">
        <div style="font-size:11px;color:var(--text-muted);margin-bottom:2px;">Total Revenue</div>
        <div style="font-size:16px;font-weight:700;color:var(--color-success);"><?= money($farm['total_revenue']) ?></div>
      </div>

      <div style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">
        📐 <?= number_format($farm['size'], 2) ?> ha &nbsp;|&nbsp; Added <?= date('d M Y', strtotime($farm['created_at'])) ?>
      </div>

      <div class="d-flex gap-8">
        <?php if (RBAC::can('farms', 'edit')): ?>
        <a href="edit.php?id=<?= $farm['id'] ?>" class="ff-btn ff-btn-outline ff-btn-sm"><i class="bi bi-pencil"></i> Edit</a>
        <?php endif; ?>
        <?php if (RBAC::can('farms', 'delete')): ?>
        <form method="POST" action="delete.php" id="del-f-<?= $farm['id'] ?>" style="display:inline;">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="id" value="<?= $farm['id'] ?>">
          <button type="button" class="ff-btn ff-btn-danger ff-btn-sm" onclick="confirmDelete('del-f-<?= $farm['id'] ?>','Delete this farm and ALL its data?')">
            <i class="bi bi-trash"></i>
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($farms)): ?>
  <div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--text-muted);">
    No farms found. <a href="create.php">Create your first farm.</a>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
