<?php
// dashboard.php — FarmFlow Main Dashboard
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/rbac_logger_helpers.php';

Auth::start();
Auth::require();

$user      = Auth::user();
$farmScope = RBAC::farmScope();   // null = all farms, int = specific farm

// ---- Farm filter clause ---
$farmWhere = $farmScope ? "AND farm_id = $farmScope" : '';

// ============================================================
// Summary Stats
// ============================================================

// Total farms
$totalFarms = DB::row("SELECT COUNT(*) AS cnt FROM farms" . ($farmScope ? " WHERE id = $farmScope" : ""))['cnt'];

// Total animals (active, not sold)
$totalAnimals = DB::row("SELECT COUNT(*) AS cnt FROM animals WHERE sold = 0 $farmWhere")['cnt'];

// Total crops (not harvested)
$totalCrops = DB::row("SELECT COUNT(*) AS cnt FROM crops WHERE harvested = 0 $farmWhere")['cnt'];

// Animals ready (maturity reached)
$animalsReady = DB::rows("SELECT id, type, birth_date, maturity_days FROM animals WHERE sold = 0 $farmWhere");
$readyAnimals = array_filter($animalsReady, fn($a) => days_left($a['birth_date'], $a['maturity_days']) <= 0);
$readyAnimalsCount = count($readyAnimals);

// Crops ready (harvest time)
$allCrops = DB::rows("SELECT id, type, planting_date, maturity_days FROM crops WHERE harvested = 0 $farmWhere");
$readyCrops = array_filter($allCrops, fn($c) => days_left($c['planting_date'], $c['maturity_days']) <= 0);
$readyCropsCount = count($readyCrops);

// Total revenue (all time)
$totalRevenue = DB::row("SELECT COALESCE(SUM(total_amount),0) AS total FROM sales" . ($farmScope ? " WHERE farm_id = $farmScope" : ""))['total'];

// Revenue this month
$revenueThisMonth = DB::row("SELECT COALESCE(SUM(total_amount),0) AS total FROM sales WHERE MONTH(sale_date) = MONTH(CURDATE()) AND YEAR(sale_date) = YEAR(CURDATE())" . ($farmScope ? " AND farm_id = $farmScope" : ""))['total'];

// Eggs today
$eggsToday = DB::row("SELECT COALESCE(SUM(quantity),0) AS total FROM egg_production WHERE date_produced = CURDATE() $farmWhere")['total'];

// Eggs this week
$eggsThisWeek = DB::row("SELECT COALESCE(SUM(quantity),0) AS total FROM egg_production WHERE date_produced >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) $farmWhere")['total'];

// Animals needing attention (sick, quarantined)
$attentionAnimals = DB::row("SELECT COUNT(*) AS cnt FROM animals WHERE health_status IN ('sick','quarantined') AND sold = 0 $farmWhere")['cnt'];

// ============================================================
// Chart Data
// ============================================================

// Monthly revenue (last 6 months)
$revenueChart = DB::rows("
    SELECT DATE_FORMAT(sale_date, '%b %Y') AS month_label,
           MONTH(sale_date) AS month_num,
           YEAR(sale_date) AS year_num,
           COALESCE(SUM(total_amount),0) AS total
    FROM sales
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    " . ($farmScope ? "AND farm_id = $farmScope" : "") . "
    GROUP BY year_num, month_num, month_label
    ORDER BY year_num, month_num
");

// Revenue by type (pie)
$revenueByType = DB::rows("
    SELECT entity_type, COALESCE(SUM(total_amount),0) AS total
    FROM sales
    " . ($farmScope ? "WHERE farm_id = $farmScope" : "") . "
    GROUP BY entity_type
");

// Daily egg production last 14 days
$eggChart = DB::rows("
    SELECT DATE_FORMAT(date_produced, '%d %b') AS day_label,
           COALESCE(SUM(quantity),0) AS total,
           COALESCE(AVG(daily_target),0) AS target
    FROM egg_production
    WHERE date_produced >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
    $farmWhere
    GROUP BY date_produced
    ORDER BY date_produced
");

// Recent animals near/ready
$animalReadiness = DB::rows("
    SELECT a.id, a.type, a.breed, a.quantity, a.birth_date, a.maturity_days, a.health_status, f.name AS farm_name
    FROM animals a
    JOIN farms f ON f.id = a.farm_id
    WHERE a.sold = 0
    " . ($farmScope ? "AND a.farm_id = $farmScope" : "") . "
    ORDER BY DATEDIFF(DATE_ADD(a.birth_date, INTERVAL a.maturity_days DAY), CURDATE())
    LIMIT 6
");

// Recent crops near/ready
$cropReadiness = DB::rows("
    SELECT c.id, c.type, c.variety, c.quantity, c.quantity_unit, c.planting_date, c.maturity_days, f.name AS farm_name
    FROM crops c
    JOIN farms f ON f.id = c.farm_id
    WHERE c.harvested = 0
    " . ($farmScope ? "AND c.farm_id = $farmScope" : "") . "
    ORDER BY DATEDIFF(DATE_ADD(c.planting_date, INTERVAL c.maturity_days DAY), CURDATE())
    LIMIT 6
");

// Recent activity logs
$recentLogs = DB::rows("
    SELECT l.*, u.name AS user_name
    FROM logs l
    LEFT JOIN users u ON u.id = l.user_id
    ORDER BY l.timestamp DESC
    LIMIT 8
");

// ============================================================
// Render
// ============================================================
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
include __DIR__ . '/templates/header.php';
?>

<!-- ============================================================
     DASHBOARD CONTENT
     ============================================================ -->

<!-- Top Stats Row -->
<div class="grid-4 mb-24">

  <!-- Total Farms -->
  <div class="stat-card green">
    <div class="stat-icon green">🌿</div>
    <div class="stat-value"><?= $totalFarms ?></div>
    <div class="stat-label">Total Farms</div>
  </div>

  <!-- Active Animals -->
  <div class="stat-card blue">
    <div class="stat-icon blue">🐄</div>
    <div class="stat-value"><?= $totalAnimals ?></div>
    <div class="stat-label">Active Animals</div>
    <?php if ($attentionAnimals > 0): ?>
    <div class="stat-change down"><i class="bi bi-exclamation-triangle"></i> <?= $attentionAnimals ?> need attention</div>
    <?php endif; ?>
  </div>

  <!-- Active Crops -->
  <div class="stat-card orange">
    <div class="stat-icon orange">🌽</div>
    <div class="stat-value"><?= $totalCrops ?></div>
    <div class="stat-label">Active Crops</div>
    <?php if ($readyCropsCount > 0): ?>
    <div class="stat-change up"><i class="bi bi-check-circle"></i> <?= $readyCropsCount ?> ready to harvest</div>
    <?php endif; ?>
  </div>

  <!-- Eggs Today -->
  <div class="stat-card teal">
    <div class="stat-icon teal">🥚</div>
    <div class="stat-value"><?= number_format($eggsToday) ?></div>
    <div class="stat-label">Eggs Today</div>
    <div class="stat-change up"><i class="bi bi-calendar-week"></i> <?= number_format($eggsThisWeek) ?> this week</div>
  </div>

</div>

<!-- Revenue Row -->
<div class="grid-4 mb-24">
  <div class="stat-card purple">
    <div class="stat-icon purple">💰</div>
    <div class="stat-value" style="font-size:20px;"><?= money($totalRevenue) ?></div>
    <div class="stat-label">Total Revenue</div>
  </div>

  <div class="stat-card green">
    <div class="stat-icon green">📈</div>
    <div class="stat-value" style="font-size:20px;"><?= money($revenueThisMonth) ?></div>
    <div class="stat-label">Revenue This Month</div>
  </div>

  <div class="stat-card orange">
    <div class="stat-icon orange">🐾</div>
    <div class="stat-value"><?= $readyAnimalsCount ?></div>
    <div class="stat-label">Animals Ready to Sell</div>
  </div>

  <div class="stat-card red">
    <div class="stat-icon red">⚠️</div>
    <div class="stat-value"><?= $attentionAnimals ?></div>
    <div class="stat-label">Animals Need Attention</div>
  </div>
</div>

<!-- Charts Row -->
<div class="grid-2 mb-24">

  <!-- Revenue Trend Chart -->
  <div class="ff-card">
    <div class="ff-card-header">
      <span class="ff-card-title"><i class="bi bi-graph-up text-success me-2"></i>Revenue Trend (6 Months)</span>
    </div>
    <div class="ff-card-body">
      <div class="chart-container" style="height:240px;">
        <canvas id="revenueChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Revenue by Type Pie -->
  <div class="ff-card">
    <div class="ff-card-header">
      <span class="ff-card-title"><i class="bi bi-pie-chart text-info me-2"></i>Revenue by Category</span>
    </div>
    <div class="ff-card-body">
      <div class="chart-container" style="height:240px;">
        <canvas id="revenueTypeChart"></canvas>
      </div>
    </div>
  </div>

</div>

<!-- Egg Production Chart -->
<div class="ff-card mb-24">
  <div class="ff-card-header">
    <span class="ff-card-title"><i class="bi bi-egg me-2" style="color:var(--color-accent)"></i>Daily Egg Production — Last 14 Days</span>
    <a href="<?= APP_URL ?>/modules/eggs/index.php" class="ff-btn ff-btn-outline ff-btn-sm">View All</a>
  </div>
  <div class="ff-card-body">
    <div class="chart-container" style="height:220px;">
      <canvas id="eggChart"></canvas>
    </div>
  </div>
</div>

<!-- Readiness Row -->
<div class="grid-2 mb-24">

  <!-- Animals Readiness -->
  <div class="ff-card">
    <div class="ff-card-header">
      <span class="ff-card-title"><i class="bi bi-heart-pulse me-2 text-danger"></i>Animal Readiness</span>
      <a href="<?= APP_URL ?>/modules/animals/index.php" class="ff-btn ff-btn-outline ff-btn-sm">View All</a>
    </div>
    <div class="ff-card-body" style="padding:0;">
      <?php foreach ($animalReadiness as $animal):
        $pct  = readiness_pct($animal['birth_date'], $animal['maturity_days']);
        $days = days_left($animal['birth_date'], $animal['maturity_days']);
        $cls  = readiness_class($days);
        $label = $days <= 0 ? '✅ Ready to Sell' : ($days <= 14 ? "⚡ {$days}d left" : "📅 " . ceil($days/7) . "w left");
      ?>
      <div style="padding:14px 22px; border-bottom:1px solid var(--border-color);"
           data-start-date="<?= e($animal['birth_date']) ?>"
           data-maturity-days="<?= (int)$animal['maturity_days'] ?>">
        <div class="d-flex align-center gap-12 mb-4">
          <div style="flex:1;">
            <div style="font-size:13px;font-weight:600;"><?= e($animal['type']) ?><?= $animal['breed'] ? ' — ' . e($animal['breed']) : '' ?></div>
            <div style="font-size:11px;color:var(--text-muted);"><?= e($animal['farm_name']) ?> &bull; Qty: <?= $animal['quantity'] ?></div>
          </div>
          <span class="ff-badge ff-badge-<?= $cls ?> readiness-badge"><?= $label ?></span>
        </div>
        <div class="ff-progress-wrap">
          <div class="ff-progress-label">
            <span>Progress</span>
            <span class="text-mono"><?= $pct ?>%</span>
          </div>
          <div class="ff-progress">
            <div class="ff-progress-bar <?= $cls ?>" style="width:<?= $pct ?>%"></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($animalReadiness)): ?>
      <div style="padding:32px;text-align:center;color:var(--text-muted);">No active animals</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Crops Readiness -->
  <div class="ff-card">
    <div class="ff-card-header">
      <span class="ff-card-title"><i class="bi bi-flower1 me-2 text-success"></i>Crop Readiness</span>
      <a href="<?= APP_URL ?>/modules/crops/index.php" class="ff-btn ff-btn-outline ff-btn-sm">View All</a>
    </div>
    <div class="ff-card-body" style="padding:0;">
      <?php foreach ($cropReadiness as $crop):
        $pct  = readiness_pct($crop['planting_date'], $crop['maturity_days']);
        $days = days_left($crop['planting_date'], $crop['maturity_days']);
        $cls  = readiness_class($days);
        $label = $days <= 0 ? '✅ Ready to Harvest' : ($days <= 14 ? "⚡ {$days}d left" : "📅 " . ceil($days/7) . "w left");
      ?>
      <div style="padding:14px 22px; border-bottom:1px solid var(--border-color);"
           data-start-date="<?= e($crop['planting_date']) ?>"
           data-maturity-days="<?= (int)$crop['maturity_days'] ?>">
        <div class="d-flex align-center gap-12 mb-4">
          <div style="flex:1;">
            <div style="font-size:13px;font-weight:600;"><?= e($crop['type']) ?><?= $crop['variety'] ? ' — ' . e($crop['variety']) : '' ?></div>
            <div style="font-size:11px;color:var(--text-muted);"><?= e($crop['farm_name']) ?> &bull; <?= e($crop['quantity']) ?> <?= e($crop['quantity_unit']) ?></div>
          </div>
          <span class="ff-badge ff-badge-<?= $cls ?> readiness-badge"><?= $label ?></span>
        </div>
        <div class="ff-progress-wrap">
          <div class="ff-progress-label">
            <span>Growth Progress</span>
            <span class="text-mono"><?= $pct ?>%</span>
          </div>
          <div class="ff-progress">
            <div class="ff-progress-bar <?= $cls ?>" style="width:<?= $pct ?>%"></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($cropReadiness)): ?>
      <div style="padding:32px;text-align:center;color:var(--text-muted);">No active crops</div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Recent Activity Logs -->
<div class="ff-card mb-24">
  <div class="ff-card-header">
    <span class="ff-card-title"><i class="bi bi-activity me-2"></i>Recent Activity</span>
    <a href="<?= APP_URL ?>/modules/logs/index.php" class="ff-btn ff-btn-outline ff-btn-sm">View All Logs</a>
  </div>
  <div class="table-responsive">
    <table class="ff-table">
      <thead>
        <tr>
          <th>Time</th>
          <th>User</th>
          <th>Action</th>
          <th>Details</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentLogs as $log):
          $actionColors = [
            'create' => 'success', 'update' => 'info', 'delete' => 'danger',
            'login' => 'secondary', 'sell' => 'warning', 'harvest' => 'success',
            'record_eggs' => 'teal', 'logout' => 'secondary',
          ];
          $ac = $actionColors[$log['action_type']] ?? 'secondary';
        ?>
        <tr>
          <td class="text-mono" style="font-size:12px;color:var(--text-muted);">
            <?= date('d M, H:i', strtotime($log['timestamp'])) ?>
          </td>
          <td><?= e($log['user_name'] ?? 'System') ?></td>
          <td><span class="ff-badge ff-badge-<?= $ac ?>"><?= e($log['action_type']) ?></span></td>
          <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <?= e($log['description']) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ============================================================
     Chart.js Initialization
     ============================================================ -->
<?php
$revLabels = json_encode(array_column($revenueChart, 'month_label'));
$revData   = json_encode(array_column($revenueChart, 'total'));
$typeLabels= json_encode(array_column($revenueByType, 'entity_type'));
$typeData  = json_encode(array_column($revenueByType, 'total'));
$eggLabels = json_encode(array_column($eggChart, 'day_label'));
$eggData   = json_encode(array_column($eggChart, 'total'));
$eggTarget = json_encode(array_column($eggChart, 'target'));
?>
<script>
window.APP_URL = '<?= APP_URL ?>';

document.addEventListener('DOMContentLoaded', () => {
  const c = ChartDefaults.getColors();
  const opts = ChartDefaults.baseOptions;

  // Revenue Trend Bar Chart
  new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
      labels: <?= $revLabels ?>,
      datasets: [{
        label: 'Revenue (₦)',
        data: <?= $revData ?>,
        backgroundColor: 'rgba(45,106,79,.7)',
        borderColor: '#2d6a4f',
        borderWidth: 1.5,
        borderRadius: 6,
      }]
    },
    options: {
      ...opts(),
      plugins: {
        ...opts().plugins,
        tooltip: {
          ...opts().plugins.tooltip,
          callbacks: {
            label: ctx => '₦' + Number(ctx.raw).toLocaleString('en-NG'),
          }
        }
      }
    }
  });

  // Revenue by Type Doughnut
  new Chart(document.getElementById('revenueTypeChart'), {
    type: 'doughnut',
    data: {
      labels: <?= $typeLabels ?>,
      datasets: [{
        data: <?= $typeData ?>,
        backgroundColor: [c.brand, c.accent, c.blue, c.purple],
        borderColor: 'var(--bg-card)',
        borderWidth: 3,
        hoverOffset: 8,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'right',
          labels: { color: c.text, font: { family: "'Sora', sans-serif", size: 12 }, padding: 16 }
        },
        tooltip: ChartDefaults.baseOptions().plugins.tooltip,
      },
    }
  });

  // Egg Production Line Chart
  new Chart(document.getElementById('eggChart'), {
    type: 'line',
    data: {
      labels: <?= $eggLabels ?>,
      datasets: [
        {
          label: 'Eggs Produced',
          data: <?= $eggData ?>,
          borderColor: '#f4a261',
          backgroundColor: 'rgba(244,162,97,.15)',
          fill: true,
          tension: 0.4,
          pointRadius: 4,
          pointBackgroundColor: '#f4a261',
        },
        {
          label: 'Daily Target',
          data: <?= $eggTarget ?>,
          borderColor: '#3182ce',
          backgroundColor: 'transparent',
          borderDash: [6, 4],
          pointRadius: 0,
          tension: 0,
        }
      ]
    },
    options: ChartDefaults.baseOptions(),
  });
});
</script>

<?php include __DIR__ . '/templates/footer.php'; ?>
