<?php
// modules/accounting/index.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';

Auth::start();
Auth::require();
RBAC::require('reports', 'view');

$scope      = RBAC::farmScope();
$farmFilter = $_GET['farm_id'] ?? '';
$year       = (int)($_GET['year'] ?? date('Y'));

$conditions = ["YEAR(sale_date) = ?"];
$params     = [$year];
if ($scope) { $conditions[] = "farm_id = ?"; $params[] = $scope; }
elseif ($farmFilter) { $conditions[] = "farm_id = ?"; $params[] = (int)$farmFilter; }
$sqlWhere = 'WHERE ' . implode(' AND ', $conditions);

// Total revenue for selected year/farm
$totalRevenue = DB::row("SELECT COALESCE(SUM(total_amount),0) AS total FROM sales $sqlWhere", $params)['total'];

// By entity type
$byType = DB::rows("SELECT entity_type, COALESCE(SUM(total_amount),0) AS total, COUNT(*) AS txn_count FROM sales $sqlWhere GROUP BY entity_type", $params);

// Monthly breakdown (12 months)
$monthly = DB::rows("
    SELECT MONTH(sale_date) AS month_num, MONTHNAME(sale_date) AS month_name,
           COALESCE(SUM(total_amount),0) AS total, COUNT(*) AS txn_count
    FROM sales $sqlWhere GROUP BY month_num, month_name ORDER BY month_num
", $params);

// Per farm breakdown
$perFarm = DB::rows("
    SELECT f.name AS farm_name, f.state, f.type,
           COALESCE(SUM(s.total_amount),0) AS total,
           COUNT(s.id) AS txn_count
    FROM sales s
    JOIN farms f ON f.id = s.farm_id
    " . (($scope || $farmFilter) ? $sqlWhere : "WHERE YEAR(sale_date) = ?") . "
    GROUP BY f.id, f.name, f.state, f.type
    ORDER BY total DESC
", $scope || $farmFilter ? $params : [$year]);

// Recent 20 sales
$recentSales = DB::rows("
    SELECT s.*, f.name AS farm_name, u.name AS recorder
    FROM sales s
    JOIN farms f ON f.id = s.farm_id
    LEFT JOIN users u ON u.id = s.recorded_by
    " . str_replace('farm_id', 's.farm_id', $sqlWhere) . "
    ORDER BY s.sale_date DESC
    LIMIT 20
", $params);

$farms = DB::rows("SELECT id, name FROM farms ORDER BY name");

// Prepare chart data
$monthLabels = [];
$monthData   = [];
$monthMap    = [];
foreach ($monthly as $m) { $monthMap[$m['month_num']] = $m['total']; }
for ($i = 1; $i <= 12; $i++) {
    $monthLabels[] = date('M', mktime(0,0,0,$i,1));
    $monthData[]   = $monthMap[$i] ?? 0;
}

$pageTitle = 'Accounting & Revenue';
$activeNav = 'accounting';
include __DIR__ . '/../../templates/header.php';
?>

<div class="ff-page-header">
  <div>
    <h2 class="ff-page-title">💰 Accounting & Revenue</h2>
    <p class="ff-page-subtitle">Financial overview and sales reports</p>
  </div>
  <?php if (RBAC::can('reports', 'export')): ?>
  <a href="export.php?year=<?= $year ?><?= $farmFilter ? '&farm_id='.$farmFilter : '' ?>" class="ff-btn ff-btn-outline">
    <i class="bi bi-download"></i> Export CSV
  </a>
  <?php endif; ?>
</div>

<!-- Filters -->
<div class="ff-card mb-24">
  <form method="GET" class="ff-search-bar" style="flex-wrap:wrap;gap:10px;">
    <div style="display:flex;align-items:center;gap:8px;">
      <label class="ff-form-label" style="margin:0;">Year:</label>
      <select name="year" class="ff-form-control" style="width:auto;padding:8px 12px;">
        <?php for ($y = date('Y'); $y >= date('Y') - 4; $y--): ?>
        <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <?php if (!$scope): ?>
    <select name="farm_id" class="ff-form-control" style="width:auto;padding:8px 12px;">
      <option value="">All Farms</option>
      <?php foreach ($farms as $f): ?>
      <option value="<?= $f['id'] ?>" <?= $farmFilter == $f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <button type="submit" class="ff-btn ff-btn-primary ff-btn-sm"><i class="bi bi-funnel"></i> Apply</button>
  </form>
</div>

<!-- Revenue Summary Cards -->
<div class="grid-4 mb-24">
  <div class="stat-card purple">
    <div class="stat-icon purple">💰</div>
    <div class="stat-value" style="font-size:20px;"><?= money($totalRevenue) ?></div>
    <div class="stat-label">Total Revenue <?= $year ?></div>
  </div>
  <?php foreach ($byType as $bt):
    $icons = ['animal'=>'🐄','crop'=>'🌽','egg'=>'🥚'];
    $colors= ['animal'=>'blue','crop'=>'green','egg'=>'teal'];
    $ic = $icons[$bt['entity_type']] ?? '💵';
    $col= $colors[$bt['entity_type']] ?? 'orange';
  ?>
  <div class="stat-card <?= $col ?>">
    <div class="stat-icon <?= $col ?>"><?= $ic ?></div>
    <div class="stat-value" style="font-size:18px;"><?= money($bt['total']) ?></div>
    <div class="stat-label"><?= ucfirst($bt['entity_type']) ?> Sales (<?= $bt['txn_count'] ?> txns)</div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Monthly Revenue Chart -->
<div class="ff-card mb-24">
  <div class="ff-card-header"><span class="ff-card-title"><i class="bi bi-graph-up me-2 text-success"></i>Monthly Revenue — <?= $year ?></span></div>
  <div class="ff-card-body">
    <div class="chart-container" style="height:260px;">
      <canvas id="monthlyChart"></canvas>
    </div>
  </div>
</div>

<!-- Per Farm -->
<?php if (!$scope): ?>
<div class="ff-card mb-24">
  <div class="ff-card-header"><span class="ff-card-title">Revenue by Farm</span></div>
  <div class="table-responsive">
    <table class="ff-table">
      <thead>
        <tr><th>Farm</th><th>State</th><th>Type</th><th>Transactions</th><th>Total Revenue</th></tr>
      </thead>
      <tbody>
        <?php foreach ($perFarm as $pf): ?>
        <tr>
          <td style="font-weight:600;"><?= e($pf['farm_name']) ?></td>
          <td><?= e($pf['state']) ?></td>
          <td><span class="ff-badge ff-badge-info"><?= e(ucfirst($pf['type'])) ?></span></td>
          <td class="text-mono"><?= $pf['txn_count'] ?></td>
          <td style="font-weight:700;color:var(--color-success);"><?= money($pf['total']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Recent Sales -->
<div class="ff-card">
  <div class="ff-card-header"><span class="ff-card-title">Recent Sales</span></div>
  <div class="table-responsive">
    <table class="ff-table" id="salesTable">
      <thead>
        <tr>
          <th data-sortable>Date</th>
          <th>Farm</th>
          <th>Type</th>
          <th data-sortable>Qty</th>
          <th data-sortable>Unit Price</th>
          <th data-sortable>Total</th>
          <th>Buyer</th>
          <th>Recorded By</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentSales as $s):
          $typeColors = ['animal'=>'blue','crop'=>'success','egg'=>'teal'];
          $tc = $typeColors[$s['entity_type']] ?? 'secondary';
        ?>
        <tr>
          <td class="text-mono"><?= date('d M Y', strtotime($s['sale_date'])) ?></td>
          <td><?= e($s['farm_name']) ?></td>
          <td><span class="ff-badge ff-badge-<?= $tc ?>"><?= e(ucfirst($s['entity_type'])) ?></span></td>
          <td class="text-mono"><?= number_format($s['quantity'], 2) ?></td>
          <td class="text-mono"><?= money($s['unit_price']) ?></td>
          <td class="text-mono" style="font-weight:700;color:var(--color-success);"><?= money($s['total_amount']) ?></td>
          <td style="font-size:12px;"><?= $s['buyer_name'] ? e($s['buyer_name']) : '—' ?></td>
          <td style="font-size:12px;"><?= e($s['recorder'] ?? 'System') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recentSales)): ?>
        <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted);">No sales recorded for this period.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const _acctLabels = <?= json_encode($monthLabels) ?>;
const _acctData   = <?= json_encode($monthData) ?>;
let   _acctInst   = null;

function buildAcctChart() {
  if (typeof Chart === 'undefined') return;
  const ctx = document.getElementById('monthlyChart');
  if (!ctx) return;

  const dark    = document.documentElement.dataset.theme === 'dark';
  const textCol = dark ? 'rgba(200,230,205,0.5)'  : 'rgba(26,46,26,0.45)';
  const gridCol = dark ? 'rgba(100,180,130,0.07)' : 'rgba(39,97,64,0.06)';
  const tipBg   = dark ? 'rgba(8,18,12,0.96)'     : 'rgba(4,12,8,0.93)';
  const font    = { family: "'Inter', system-ui, sans-serif", size: 11 };

  _acctInst?.destroy();

  _acctInst = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: _acctLabels,
      datasets: [{
        label: 'Revenue (₦)',
        data: _acctData,
        backgroundColor: _acctData.map(v => v > 0
          ? (dark ? 'rgba(64,145,108,0.82)' : 'rgba(45,106,79,0.78)')
          : 'rgba(113,128,150,0.22)'),
        borderRadius: 6,
        borderSkipped: false,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 700 },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: tipBg,
          titleColor: '#c8e6cd',
          bodyColor: 'rgba(200,230,205,0.7)',
          borderColor: 'rgba(100,180,130,0.2)',
          borderWidth: 1, padding: 12, cornerRadius: 10,
          titleFont: { ...font, weight: '600', size: 12 }, bodyFont: font,
          callbacks: { label: c => '  ₦' + Number(c.raw).toLocaleString('en-NG') }
        }
      },
      scales: {
        x: {
          ticks: { color: textCol, font, maxRotation: 0, padding: 6 },
          grid:  { color: gridCol, drawTicks: false },
          border:{ display: false }
        },
        y: {
          ticks: {
            color: textCol, font, padding: 6,
            callback: v => '₦' + Intl.NumberFormat('en-NG', { notation: 'compact' }).format(v)
          },
          grid:  { color: gridCol },
          border:{ display: false },
          beginAtZero: true
        }
      }
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  if (typeof TableSorter !== 'undefined') TableSorter.init('salesTable');
  buildAcctChart();
});

document.addEventListener('themechange', () => setTimeout(buildAcctChart, 80));
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>