<?php
// modules/animals/index.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';

Auth::start();
Auth::require();
RBAC::require('animals', 'view');

$farmWhere = RBAC::farmScope() ? "WHERE a.farm_id = " . RBAC::farmScope() : "";

// Filters
$filterHealth = $_GET['health'] ?? '';
$filterFarm   = $_GET['farm_id'] ?? '';
$filterStatus = $_GET['status'] ?? '';  // 'ready', 'near', 'pending'
$search       = $_GET['q'] ?? '';

$where = [];
if (RBAC::farmScope()) $where[] = "a.farm_id = " . RBAC::farmScope();
if ($filterHealth) $where[] = "a.health_status = " . DB::getInstance()->quote($filterHealth);
if ($filterFarm && !RBAC::farmScope()) $where[] = "a.farm_id = " . (int)$filterFarm;
if ($search) $where[] = "(a.type LIKE '%" . DB::getInstance()->quote(trim($search), PDO::PARAM_STR) . "%' OR a.breed LIKE '%" . DB::getInstance()->quote(trim($search), PDO::PARAM_STR) . "%')";

// Build SQL (using parameterized for safety)
$params = [];
$sqlWhere = '';
$conditions = [];

if (RBAC::farmScope()) { $conditions[] = "a.farm_id = ?"; $params[] = RBAC::farmScope(); }
if ($filterHealth) { $conditions[] = "a.health_status = ?"; $params[] = $filterHealth; }
if ($filterFarm && !RBAC::farmScope()) { $conditions[] = "a.farm_id = ?"; $params[] = (int)$filterFarm; }
if ($search) { $conditions[] = "(a.type LIKE ? OR a.breed LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$sqlWhere = $conditions ? 'WHERE a.sold = 0 AND ' . implode(' AND ', $conditions) : 'WHERE a.sold = 0';

$animals = DB::rows("
    SELECT a.*, f.name AS farm_name
    FROM animals a
    JOIN farms f ON f.id = a.farm_id
    $sqlWhere
    ORDER BY a.created_at DESC
", $params);

// Farms for filter dropdown
$farms = DB::rows("SELECT id, name FROM farms ORDER BY name");

$pageTitle = 'Animals';
$activeNav = 'animals';
include __DIR__ . '/../../templates/header.php';
?>

<div class="ff-page-header">
  <div>
    <h2 class="ff-page-title">🐄 Animals</h2>
    <p class="ff-page-subtitle">Livestock inventory and readiness tracker</p>
  </div>
  <?php if (RBAC::can('animals', 'create')): ?>
  <a href="create.php" class="ff-btn ff-btn-primary">
    <i class="bi bi-plus-circle"></i> Add Animal
  </a>
  <?php endif; ?>
</div>

<!-- Filters -->
<div class="ff-card mb-24">
  <form method="GET" class="ff-search-bar" style="gap:10px;flex-wrap:wrap;">
    <div class="ff-search-wrap" style="min-width:220px;">
      <i class="bi bi-search search-icon"></i>
      <input class="ff-search-input w-100" type="text" name="q" placeholder="Search by type or breed…" value="<?= e($search) ?>">
    </div>
    <?php if (!RBAC::farmScope()): ?>
    <select name="farm_id" class="ff-form-control" style="width:auto;padding:8px 12px;">
      <option value="">All Farms</option>
      <?php foreach ($farms as $f): ?>
      <option value="<?= $f['id'] ?>" <?= $filterFarm == $f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <select name="health" class="ff-form-control" style="width:auto;padding:8px 12px;">
      <option value="">All Health</option>
      <option value="healthy" <?= $filterHealth === 'healthy' ? 'selected' : '' ?>>Healthy</option>
      <option value="sick" <?= $filterHealth === 'sick' ? 'selected' : '' ?>>Sick</option>
      <option value="recovering" <?= $filterHealth === 'recovering' ? 'selected' : '' ?>>Recovering</option>
      <option value="quarantined" <?= $filterHealth === 'quarantined' ? 'selected' : '' ?>>Quarantined</option>
    </select>
    <button type="submit" class="ff-btn ff-btn-primary ff-btn-sm"><i class="bi bi-funnel"></i> Filter</button>
    <a href="index.php" class="ff-btn ff-btn-outline ff-btn-sm">Clear</a>
  </form>
</div>

<!-- Animals Table -->
<div class="ff-card">
  <div class="table-responsive">
    <table class="ff-table" id="animalsTable">
      <thead>
        <tr>
          <th data-sortable>#</th>
          <th data-sortable>Type / Breed</th>
          <th data-sortable>Farm</th>
          <th data-sortable>Qty</th>
          <th data-sortable>Birth Date</th>
          <th data-sortable>Maturity</th>
          <th>Readiness</th>
          <th data-sortable>Health</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($animals as $i => $a):
          $days = days_left($a['birth_date'], $a['maturity_days']);
          $pct  = readiness_pct($a['birth_date'], $a['maturity_days']);
          $cls  = readiness_class($days);
          $hcls = health_class($a['health_status']);
          if ($days <= 0) $readyLabel = '✅ Ready';
          elseif ($days <= 7) $readyLabel = "⚡ {$days}d";
          elseif ($days <= 14) $readyLabel = ceil($days/7) . "w left ⚠️";
          else $readyLabel = ceil($days/7) . "w left";
        ?>
        <tr>
          <td class="text-mono" style="color:var(--text-muted);"><?= $i + 1 ?></td>
          <td>
            <div style="font-weight:600;"><?= e($a['type']) ?></div>
            <div style="font-size:11px;color:var(--text-muted);"><?= $a['breed'] ? e($a['breed']) : '—' ?></div>
          </td>
          <td><?= e($a['farm_name']) ?></td>
          <td class="text-mono"><?= number_format($a['quantity']) ?></td>
          <td><?= date('d M Y', strtotime($a['birth_date'])) ?></td>
          <td><?= $a['maturity_days'] ?>d</td>
          <td style="min-width:150px;">
            <div class="ff-progress-wrap">
              <div class="ff-progress-label">
                <span><?= $readyLabel ?></span>
                <span class="text-mono"><?= $pct ?>%</span>
              </div>
              <div class="ff-progress">
                <div class="ff-progress-bar <?= $cls ?>" style="width:<?= $pct ?>%"></div>
              </div>
            </div>
          </td>
          <td><span class="ff-badge ff-badge-<?= $hcls ?>"><?= e($a['health_status']) ?></span></td>
          <td>
            <div class="d-flex gap-8">
              <?php if (RBAC::can('animals', 'edit')): ?>
              <a href="edit.php?id=<?= $a['id'] ?>" class="ff-btn ff-btn-outline ff-btn-sm" title="Edit"><i class="bi bi-pencil"></i></a>
              <?php endif; ?>
              <?php if (RBAC::can('animals', 'sell') && $days <= 0): ?>
              <a href="sell.php?id=<?= $a['id'] ?>" class="ff-btn ff-btn-success ff-btn-sm" title="Record Sale"><i class="bi bi-currency-dollar"></i></a>
              <?php endif; ?>
              <?php if (RBAC::can('animals', 'delete')): ?>
              <form method="POST" action="delete.php" id="del-a-<?= $a['id'] ?>" style="display:inline;">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                <button type="button" class="ff-btn ff-btn-danger ff-btn-sm" onclick="confirmDelete('del-a-<?= $a['id'] ?>')">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($animals)): ?>
        <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted);">No animals found. <a href="create.php">Add one?</a></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  TableSorter.init('animalsTable');
  initLiveSearch('animalSearch', 'animalsTable');
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
