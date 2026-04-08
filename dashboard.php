<?php
// dashboard.php — FarmFlow Premium Dashboard v2
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/rbac_logger_helpers.php';

Auth::start();
Auth::require();

// ── PHP helpers needed by this page ──────────────────────────
// days_left and readiness_pct should be in rbac_logger_helpers.php.
// weeksLabel was only defined in JS — adding it here as PHP.
if (!function_exists('days_left')) {
    function days_left(string $startDate, int $maturityDays): int {
        $maturity = (new DateTime($startDate))->modify("+{$maturityDays} days");
        $today    = new DateTime('today');
        return (int)$today->diff($maturity)->days * ($maturity >= $today ? 1 : -1);
    }
}
if (!function_exists('readiness_pct')) {
    function readiness_pct(string $startDate, int $maturityDays): int {
        $elapsed = (new DateTime('today'))->diff(new DateTime($startDate))->days;
        return min(100, (int)round(($elapsed / max(1, $maturityDays)) * 100));
    }
}
if (!function_exists('weeksLabel')) {
    function weeksLabel(int $days): string {
        if ($days <= 0) return 'Ready';
        $w = intdiv($days, 7);
        $r = $days % 7;
        if ($w === 0) return "{$r}d left";
        return $r > 0 ? "{$w}w {$r}d" : "{$w}w left";
    }
}
if (!function_exists('money')) {
    function money(float $n): string {
        return '₦' . number_format((int)round($n));
    }
}

// ── Farm scope ────────────────────────────────────────────────
$scope = RBAC::farmScope();
$fw    = $scope ? "AND farm_id = $scope" : '';

// ---- Core Stats ----
$totalFarms    = DB::row("SELECT COUNT(*) AS n FROM farms" . ($scope ? " WHERE id=$scope" : ""))['n'];
$totalAnimals  = DB::row("SELECT COUNT(*) AS n FROM animals WHERE sold=0 $fw")['n'];
$totalCrops    = DB::row("SELECT COUNT(*) AS n FROM crops WHERE harvested=0 $fw")['n'];
$eggsToday     = DB::row("SELECT COALESCE(SUM(quantity),0) AS n FROM egg_production WHERE date_produced=CURDATE() $fw")['n'];
$eggsWeek      = DB::row("SELECT COALESCE(SUM(quantity),0) AS n FROM egg_production WHERE date_produced>=DATE_SUB(CURDATE(),INTERVAL 7 DAY) $fw")['n'];

$allAnimals   = DB::rows("SELECT birth_date, maturity_days FROM animals WHERE sold=0 $fw");
$readyAnimals = count(array_filter($allAnimals, fn($a) => days_left($a['birth_date'], $a['maturity_days']) <= 0));
$soonAnimals  = count(array_filter($allAnimals, fn($a) => ($d = days_left($a['birth_date'], $a['maturity_days'])) > 0 && $d <= 14));

$allCrops    = DB::rows("SELECT planting_date, maturity_days FROM crops WHERE harvested=0 $fw");
$readyCrops  = count(array_filter($allCrops, fn($c) => days_left($c['planting_date'], $c['maturity_days']) <= 0));
$soonCrops   = count(array_filter($allCrops, fn($c) => ($d = days_left($c['planting_date'], $c['maturity_days'])) > 0 && $d <= 14));

$attentionAnimals = DB::row("SELECT COUNT(*) AS n FROM animals WHERE health_status IN ('sick','quarantined') AND sold=0 $fw")['n'];

// ---- Revenue ----
$totalRevenue = DB::row("SELECT COALESCE(SUM(total_amount),0) AS t FROM sales" . ($scope ? " WHERE farm_id=$scope" : ""))['t'];
$revThisMonth = DB::row("SELECT COALESCE(SUM(total_amount),0) AS t FROM sales WHERE MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE())" . ($scope ? " AND farm_id=$scope" : ""))['t'];
$revLastMonth = DB::row("SELECT COALESCE(SUM(total_amount),0) AS t FROM sales WHERE MONTH(sale_date)=MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) AND YEAR(sale_date)=YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))" . ($scope ? " AND farm_id=$scope" : ""))['t'];
$revChange    = $revLastMonth > 0 ? round((($revThisMonth - $revLastMonth) / $revLastMonth) * 100, 1) : 0;

// ---- Health breakdown ----
$healthBreakdown = DB::rows("SELECT health_status, COUNT(*) AS n FROM animals WHERE sold=0 $fw GROUP BY health_status ORDER BY n DESC");

// ---- Egg trend chart (14 days) ----
$eggChart = DB::rows("
  SELECT DATE_FORMAT(date_produced,'%d %b') AS d, COALESCE(SUM(quantity),0) AS qty, COALESCE(AVG(daily_target),0) AS tgt
  FROM egg_production WHERE date_produced>=DATE_SUB(CURDATE(),INTERVAL 14 DAY) $fw
  GROUP BY date_produced ORDER BY date_produced
");

// ---- Revenue last 6 months ----
$revChart = DB::rows("
  SELECT DATE_FORMAT(sale_date,'%b') AS mo, MONTH(sale_date) AS mn, YEAR(sale_date) AS yr,
         COALESCE(SUM(total_amount),0) AS t
  FROM sales WHERE sale_date>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH)" .
  ($scope ? " AND farm_id=$scope" : "") . "
  GROUP BY yr,mn,mo ORDER BY yr,mn
");

// ---- Revenue by entity type ----
$revByType = DB::rows("SELECT entity_type, COALESCE(SUM(total_amount),0) AS t FROM sales" .
  ($scope ? " WHERE farm_id=$scope" : "") . " GROUP BY entity_type");

// ---- Animal readiness (top 5) ----
$animalReady = DB::rows("
  SELECT a.id,a.type,a.breed,a.quantity,a.birth_date,a.maturity_days,a.health_status,f.name AS farm
  FROM animals a JOIN farms f ON f.id=a.farm_id
  WHERE a.sold=0" . ($scope ? " AND a.farm_id=$scope" : "") . "
  ORDER BY DATEDIFF(DATE_ADD(a.birth_date,INTERVAL a.maturity_days DAY),CURDATE()) ASC LIMIT 5
");

// ---- Crop readiness (top 5) ----
$cropReady = DB::rows("
  SELECT c.id,c.type,c.variety,c.quantity,c.quantity_unit,c.planting_date,c.maturity_days,f.name AS farm
  FROM crops c JOIN farms f ON f.id=c.farm_id
  WHERE c.harvested=0" . ($scope ? " AND c.farm_id=$scope" : "") . "
  ORDER BY DATEDIFF(DATE_ADD(c.planting_date,INTERVAL c.maturity_days DAY),CURDATE()) ASC LIMIT 5
");

// ---- Egg production today vs yesterday ----
$eggsYesterday = DB::row("SELECT COALESCE(SUM(quantity),0) AS n FROM egg_production WHERE date_produced=DATE_SUB(CURDATE(),INTERVAL 1 DAY) $fw")['n'];
$eggChange     = $eggsYesterday > 0 ? round((($eggsToday - $eggsYesterday) / $eggsYesterday) * 100, 1) : 0;

// ---- Recent logs ----
$recentLogs = DB::rows("
  SELECT l.*, u.name AS uname FROM logs l
  LEFT JOIN users u ON u.id=l.user_id
  ORDER BY l.timestamp DESC LIMIT 7
");

// ---- Top farm by revenue ----
$topFarm = DB::row("
  SELECT f.name, COALESCE(SUM(s.total_amount),0) AS t
  FROM sales s JOIN farms f ON f.id=s.farm_id
  GROUP BY f.id,f.name ORDER BY t DESC LIMIT 1
");

// JSON for charts
$eggLabels = json_encode(array_column($eggChart, 'd'));
$eggQty    = json_encode(array_column($eggChart, 'qty'));
$eggTgt    = json_encode(array_map(fn($r) => round((float)$r['tgt']), $eggChart));
$revLabels = json_encode(array_column($revChart, 'mo'));
$revData   = json_encode(array_column($revChart, 't'));
$typeLabels = json_encode(array_column($revByType, 'entity_type'));
$typeData   = json_encode(array_column($revByType, 't'));

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
include __DIR__ . '/templates/header.php';
?>

<script>window.APP_URL = '<?= APP_URL ?>';</script>

<!-- ================================================================
     ROW 1 — 4 Primary KPI cards
     ================================================================ -->
<div class="stat-grid anim-1">

  <!-- Revenue This Month -->
  <div class="stat-card c-emerald">
    <div class="stat-glow"></div>
    <div class="stat-header">
      <div class="stat-icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
        </svg>
      </div>
      <span class="stat-pill <?= $revChange >= 0 ? 'up' : 'down' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <?= $revChange >= 0 ? '<polyline points="18 15 12 9 6 15"/>' : '<polyline points="6 9 12 15 18 9"/>' ?>
        </svg>
        <?= abs($revChange) ?>%
      </span>
    </div>
    <div class="stat-value"><?= money($revThisMonth) ?></div>
    <div class="stat-label">Revenue This Month</div>
    <div class="stat-footer">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      Total all-time: <b><?= money($totalRevenue) ?></b>
    </div>
  </div>

  <!-- Active Animals -->
  <div class="stat-card c-azure">
    <div class="stat-glow"></div>
    <div class="stat-header">
      <div class="stat-icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <path d="M3 10c0-2.5 1.5-4 4-4 .5 0 1 .1 1.5.3M21 10c0-2.5-1.5-4-4-4-.5 0-1 .1-1.5.3M8.5 6.3C9 5 10 4 12 4s3 1 3.5 2.3M6 14c0 3.3 2.7 6 6 6s6-2.7 6-6"/>
        </svg>
      </div>
      <?php if ($attentionAnimals > 0): ?>
      <span class="stat-pill down">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
        <?= $attentionAnimals ?> sick
      </span>
      <?php else: ?>
      <span class="stat-pill neu">Healthy</span>
      <?php endif; ?>
    </div>
    <div class="stat-value"><?= number_format($totalAnimals) ?></div>
    <div class="stat-label">Active Animals</div>
    <div class="stat-footer">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      <b><?= $readyAnimals ?></b> ready &nbsp;·&nbsp; <b><?= $soonAnimals ?></b> within 2 weeks
    </div>
  </div>

  <!-- Active Crops -->
  <div class="stat-card c-amber">
    <div class="stat-glow"></div>
    <div class="stat-header">
      <div class="stat-icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <path d="M12 22V12M12 12C12 7 8 4 3 4c0 5 3 9 9 9M12 12c0-5 4-8 9-8-1 5-4 8-9 8"/>
        </svg>
      </div>
      <?php if ($readyCrops > 0): ?>
      <span class="stat-pill up">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>
        <?= $readyCrops ?> ready
      </span>
      <?php else: ?>
      <span class="stat-pill neu">Growing</span>
      <?php endif; ?>
    </div>
    <div class="stat-value"><?= $totalCrops ?></div>
    <div class="stat-label">Active Crops</div>
    <div class="stat-footer">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M3 3l18 18M10.5 6H19v8.5"/></svg>
      <b><?= $soonCrops ?></b> maturing within 2 weeks
    </div>
  </div>

  <!-- Eggs Today -->
  <div class="stat-card c-gold">
    <div class="stat-glow"></div>
    <div class="stat-header">
      <div class="stat-icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <ellipse cx="12" cy="13" rx="6" ry="8"/>
        </svg>
      </div>
      <span class="stat-pill <?= $eggChange >= 0 ? 'up' : 'down' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <?= $eggChange >= 0 ? '<polyline points="18 15 12 9 6 15"/>' : '<polyline points="6 9 12 15 18 9"/>' ?>
        </svg>
        <?= abs($eggChange) ?>%
      </span>
    </div>
    <div class="stat-value"><?= number_format($eggsToday) ?></div>
    <div class="stat-label">Eggs Today</div>
    <div class="stat-footer">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      This week: <b><?= number_format($eggsWeek) ?></b> eggs collected
    </div>
  </div>

</div>

<!-- ================================================================
     ROW 2 — Secondary KPI mini-cards
     ================================================================ -->
<div class="grid-3 mb-20 anim-2">

  <!-- Farms -->
  <div class="ff-card" style="padding:0;">
    <div class="ff-card-body" style="display:flex;align-items:center;gap:16px;padding:18px 20px;">
      <div style="width:44px;height:44px;border-radius:var(--radius-md);background:rgba(74,158,110,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg fill="none" viewBox="0 0 24 24" stroke="var(--forest-400)" stroke-width="1.8" width="22" height="22">
          <path d="M3 9.5L12 3l9 6.5V21a1 1 0 01-1 1H4a1 1 0 01-1-1V9.5z"/><path d="M9 21V12h6v9"/>
        </svg>
      </div>
      <div style="flex:1;">
        <div style="font-size:24px;font-weight:800;font-family:var(--ff-mono);color:var(--text-primary);line-height:1;"><?= $totalFarms ?></div>
        <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.55px;margin-top:2px;">Registered Farms</div>
      </div>
      <?php if ($topFarm): ?>
      <div style="text-align:right;font-size:11px;color:var(--text-muted);">
        <div style="font-weight:600;color:var(--text-secondary);font-size:12px;"><?= e($topFarm['name']) ?></div>
        <div>top earner</div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Animals Ready -->
  <div class="ff-card" style="padding:0;">
    <div class="ff-card-body" style="display:flex;align-items:center;gap:16px;padding:18px 20px;">
      <div style="width:44px;height:44px;border-radius:var(--radius-md);background:var(--c-success-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg fill="none" viewBox="0 0 24 24" stroke="var(--c-success)" stroke-width="1.8" width="22" height="22">
          <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
      </div>
      <div style="flex:1;">
        <div style="font-size:24px;font-weight:800;font-family:var(--ff-mono);color:var(--c-success);line-height:1;"><?= $readyAnimals ?></div>
        <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.55px;margin-top:2px;">Animals Ready to Sell</div>
      </div>
      <?php if ($attentionAnimals > 0): ?>
      <span class="ff-badge badge-danger"><?= $attentionAnimals ?> need care</span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Crops Ready -->
  <div class="ff-card" style="padding:0;">
    <div class="ff-card-body" style="display:flex;align-items:center;gap:16px;padding:18px 20px;">
      <div style="width:44px;height:44px;border-radius:var(--radius-md);background:var(--c-warning-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg fill="none" viewBox="0 0 24 24" stroke="var(--c-warning)" stroke-width="1.8" width="22" height="22">
          <path d="M3 3l18 18M10.5 6H19v8.5"/>
          <path d="M12 22V12M12 12C12 7 8 4 3 4c0 5 3 9 9 9"/>
        </svg>
      </div>
      <div style="flex:1;">
        <div style="font-size:24px;font-weight:800;font-family:var(--ff-mono);color:var(--c-warning);line-height:1;"><?= $readyCrops ?></div>
        <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.55px;margin-top:2px;">Crops Ready to Harvest</div>
      </div>
      <?php if ($soonCrops > 0): ?>
      <span class="ff-badge badge-warning"><?= $soonCrops ?> soon</span>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- ================================================================
     ROW 3 — Revenue Bar + Donut side by side
     ================================================================ -->
<div class="grid-2 mb-20 anim-3">

  <!-- Revenue Bar -->
  <div class="ff-card">
    <div class="ff-card-header">
      <div class="ff-card-title">
        <div class="card-icon-badge cib-emerald">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        </div>
        Revenue Trend — Last 6 Months
      </div>
      <a href="<?= APP_URL ?>/modules/accounting/index.php" class="card-link-btn">
        View Report
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </a>
    </div>
    <div class="ff-card-body">
      <div style="display:flex;gap:20px;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid var(--border);">
        <div>
          <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;font-weight:600;">This Month</div>
          <div style="font-size:18px;font-weight:800;font-family:var(--ff-mono);color:var(--text-primary);letter-spacing:-0.03em;"><?= money($revThisMonth) ?></div>
        </div>
        <div>
          <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;font-weight:600;">All Time</div>
          <div style="font-size:18px;font-weight:800;font-family:var(--ff-mono);color:var(--forest-400);letter-spacing:-0.03em;"><?= money($totalRevenue) ?></div>
        </div>
        <div style="margin-left:auto;align-self:center;">
          <span class="ff-badge <?= $revChange >= 0 ? 'badge-success' : 'badge-danger' ?>" style="font-size:12px;padding:5px 12px;">
            <?= $revChange >= 0 ? '+' : '' ?><?= $revChange ?>% vs last month
          </span>
        </div>
      </div>
      <div style="height:230px;position:relative;">
        <canvas id="revChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Revenue donut + health breakdown -->
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- Donut -->
    <div class="ff-card" style="flex:1;">
      <div class="ff-card-header">
        <div class="ff-card-title">
          <div class="card-icon-badge cib-azure">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
          </div>
          Revenue Split
        </div>
      </div>
      <div class="ff-card-body" style="display:flex;align-items:center;gap:20px;">
        <div style="position:relative;height:160px;width:160px;flex-shrink:0;">
          <canvas id="typeChart"></canvas>
        </div>
        <div style="flex:1;display:flex;flex-direction:column;gap:10px;">
          <?php
          $typeColors = ['animal'=>['var(--forest-400)','rgba(74,158,110,0.12)'],'crop'=>['var(--gold-500)','rgba(224,148,32,0.12)'],'egg'=>['#2563eb','rgba(37,99,235,0.1)']];
          $totalRevT  = array_sum(array_column($revByType,'t'));
          foreach ($revByType as $rt):
            [$clr,$bg] = $typeColors[$rt['entity_type']] ?? ['#888','rgba(128,128,128,0.1)'];
            $pct = $totalRevT > 0 ? round(($rt['t'] / $totalRevT) * 100) : 0;
          ?>
          <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:8px;height:8px;border-radius:50%;background:<?= $clr ?>;flex-shrink:0;"></div>
            <div style="flex:1;">
              <div style="font-size:12px;font-weight:600;color:var(--text-primary);text-transform:capitalize;"><?= ucfirst($rt['entity_type']) ?></div>
              <div style="font-size:11px;color:var(--text-muted);font-family:var(--ff-mono);"><?= money($rt['t']) ?></div>
            </div>
            <span style="font-size:12px;font-weight:700;font-family:var(--ff-mono);color:var(--text-secondary);"><?= $pct ?>%</span>
          </div>
          <?php endforeach; ?>
          <?php if (empty($revByType)): ?>
          <div style="color:var(--text-muted);font-size:13px;">No sales recorded yet.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Animal Health card -->
    <div class="ff-card">
      <div class="ff-card-header">
        <div class="ff-card-title">
          <div class="card-icon-badge cib-rose">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
          </div>
          Animal Health Status
        </div>
      </div>
      <div class="ff-card-body" style="padding:14px 20px;">
        <?php
        $hColors = ['healthy'=>['badge-success','#22c55e'],'sick'=>['badge-danger','#ef4444'],'recovering'=>['badge-warning','#f59e0b'],'quarantined'=>['badge-info','#3b82f6'],'deceased'=>['badge-neutral','#6b7280']];
        foreach ($healthBreakdown as $h):
          [$badge,$bar] = $hColors[$h['health_status']] ?? ['badge-neutral','#888'];
          $pct = $totalAnimals > 0 ? round(($h['n'] / $totalAnimals) * 100) : 0;
        ?>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
          <span class="ff-badge <?= $badge ?>" style="min-width:90px;justify-content:center;font-size:10.5px;"><?= e(ucfirst($h['health_status'])) ?></span>
          <div style="flex:1;height:5px;background:var(--border);border-radius:99px;overflow:hidden;">
            <div style="height:100%;width:<?= $pct ?>%;background:<?= $bar ?>;border-radius:99px;transition:width .9s cubic-bezier(.4,0,.2,1);"></div>
          </div>
          <span style="font-size:11.5px;font-weight:700;font-family:var(--ff-mono);color:var(--text-secondary);min-width:32px;text-align:right;"><?= $h['n'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($healthBreakdown)): ?>
        <div style="color:var(--text-muted);font-size:13px;text-align:center;padding:12px 0;">No animals on record.</div>
        <?php endif; ?>
      </div>
    </div>

  </div>

</div>

<!-- ================================================================
     ROW 4 — Egg Production Area Chart (full width)
     ================================================================ -->
<div class="ff-card mb-20 anim-4">
  <div class="ff-card-header">
    <div class="ff-card-title">
      <div class="card-icon-badge cib-gold">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><ellipse cx="12" cy="13" rx="6" ry="8"/></svg>
      </div>
      Daily Egg Production — Last 14 Days
    </div>
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="display:flex;align-items:center;gap:14px;font-size:11.5px;color:var(--text-muted);font-family:var(--ff-mono);">
        <span style="display:flex;align-items:center;gap:5px;">
          <span style="width:20px;height:2px;background:var(--gold-400);display:inline-block;border-radius:2px;"></span>Collected
        </span>
        <span style="display:flex;align-items:center;gap:5px;">
          <span style="width:20px;height:0;border-top:2px dashed #60a5fa;display:inline-block;"></span>Target
        </span>
      </div>
      <a href="<?= APP_URL ?>/modules/eggs/index.php" class="card-link-btn">
        View All
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </a>
    </div>
  </div>
  <div class="ff-card-body">
    <div style="height:220px;position:relative;">
      <canvas id="eggChart"></canvas>
    </div>
  </div>
</div>

<!-- ================================================================
     ROW 5 — Animal + Crop Readiness side by side
     ================================================================ -->
<div class="grid-2 mb-20 anim-5">

  <!-- Animals -->
  <div class="ff-card">
    <div class="ff-card-header">
      <div class="ff-card-title">
        <div class="card-icon-badge cib-azure">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path d="M3 10c0-2.5 1.5-4 4-4 .5 0 1 .1 1.5.3M21 10c0-2.5-1.5-4-4-4-.5 0-1 .1-1.5.3M8.5 6.3C9 5 10 4 12 4s3 1 3.5 2.3M6 14c0 3.3 2.7 6 6 6s6-2.7 6-6"/>
          </svg>
        </div>
        Animal Readiness
      </div>
      <a href="<?= APP_URL ?>/modules/animals/index.php" class="card-link-btn">
        View All
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </a>
    </div>
    <div>
      <?php foreach ($animalReady as $a):
        $days = days_left($a['birth_date'], $a['maturity_days']);
        $pct  = readiness_pct($a['birth_date'], $a['maturity_days']);
        $chp  = $days <= 0 ? 'chip-ready' : ($days <= 14 ? 'chip-soon' : 'chip-growing');
        $bar  = $days <= 0 ? 'p-ready'    : ($days <= 14 ? 'p-soon'    : 'p-growing');
        $lbl  = $days <= 0 ? 'Ready to Sell' : weeksLabel($days);
      ?>
      <div class="prog-item">
        <div class="prog-top-row">
          <div>
            <div class="prog-name"><?= e($a['type']) ?><?= $a['breed'] ? ' <span style="font-weight:400;color:var(--text-muted);">— '.e($a['breed']).'</span>' : '' ?></div>
            <div class="prog-meta"><?= e($a['farm']) ?> &nbsp;·&nbsp; <?= number_format($a['quantity']) ?> head</div>
          </div>
          <span class="readiness-chip <?= $chp ?>"><?= $lbl ?></span>
        </div>
        <div class="prog-track">
          <div class="prog-fill <?= $bar ?>" style="width:<?= $pct ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($animalReady)): ?>
      <div style="padding:36px;text-align:center;color:var(--text-muted);font-size:13px;">No active animals.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Crops -->
  <div class="ff-card">
    <div class="ff-card-header">
      <div class="ff-card-title">
        <div class="card-icon-badge cib-emerald">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path d="M12 22V12M12 12C12 7 8 4 3 4c0 5 3 9 9 9M12 12c0-5 4-8 9-8-1 5-4 8-9 8"/>
          </svg>
        </div>
        Crop Readiness
      </div>
      <a href="<?= APP_URL ?>/modules/crops/index.php" class="card-link-btn">
        View All
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </a>
    </div>
    <div>
      <?php foreach ($cropReady as $c):
        $days = days_left($c['planting_date'], $c['maturity_days']);
        $pct  = readiness_pct($c['planting_date'], $c['maturity_days']);
        $chp  = $days <= 0 ? 'chip-ready' : ($days <= 14 ? 'chip-soon' : 'chip-growing');
        $bar  = $days <= 0 ? 'p-ready'    : ($days <= 14 ? 'p-soon'    : 'p-growing');
        $lbl  = $days <= 0 ? 'Ready to Harvest' : weeksLabel($days);
      ?>
      <div class="prog-item">
        <div class="prog-top-row">
          <div>
            <div class="prog-name"><?= e($c['type']) ?><?= $c['variety'] ? ' <span style="font-weight:400;color:var(--text-muted);">— '.e($c['variety']).'</span>' : '' ?></div>
            <div class="prog-meta"><?= e($c['farm']) ?> &nbsp;·&nbsp; <?= e($c['quantity']) ?> <?= e($c['quantity_unit']) ?></div>
          </div>
          <span class="readiness-chip <?= $chp ?>"><?= $lbl ?></span>
        </div>
        <div class="prog-track">
          <div class="prog-fill <?= $bar ?>" style="width:<?= $pct ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($cropReady)): ?>
      <div style="padding:36px;text-align:center;color:var(--text-muted);font-size:13px;">No active crops.</div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- ================================================================
     ROW 6 — Recent Activity log
     ================================================================ -->
<div class="ff-card anim-6">
  <div class="ff-card-header">
    <div class="ff-card-title">
      <div class="card-icon-badge" style="background:rgba(107,143,122,0.1);color:var(--text-secondary);">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      </div>
      Recent Activity
    </div>
    <a href="<?= APP_URL ?>/modules/logs/index.php" class="card-link-btn">
      View Full Log
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </a>
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
        <?php foreach ($recentLogs as $lg):
          $aMap = [
            'create'      => ['badge-success', 'Created'],
            'update'      => ['badge-info',    'Updated'],
            'delete'      => ['badge-danger',  'Deleted'],
            'login'       => ['badge-neutral', 'Login'],
            'logout'      => ['badge-neutral', 'Logout'],
            'sell'        => ['badge-gold',    'Sold'],
            'harvest'     => ['badge-success', 'Harvested'],
            'record_eggs' => ['badge-info',    'Eggs Recorded'],
            'email_sent'  => ['badge-neutral', 'Email'],
          ];
          [$badgeCls, $label] = $aMap[$lg['action_type']] ?? ['badge-neutral', ucfirst($lg['action_type'])];
        ?>
        <tr>
          <td class="text-mono" style="font-size:11.5px;color:var(--text-muted);white-space:nowrap;">
            <?= date('d M, H:i', strtotime($lg['timestamp'])) ?>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--forest-400),var(--forest-200));display:flex;align-items:center;justify-content:center;font-size:9.5px;font-weight:700;color:#fff;flex-shrink:0;">
                <?= strtoupper(substr($lg['uname'] ?? 'S', 0, 2)) ?>
              </div>
              <span style="font-size:13px;font-weight:500;"><?= e($lg['uname'] ?? 'System') ?></span>
            </div>
          </td>
          <td><span class="ff-badge <?= $badgeCls ?>"><?= $label ?></span></td>
          <td style="max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text-secondary);font-size:13px;">
            <?= e($lg['description']) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ================================================================
     Chart.js — loaded inline so we control timing.
     We load Chart.js from CDN then immediately build charts,
     avoiding any DOMContentLoaded race with footer.php.
     ================================================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
  // Data from PHP
  const _revLabels  = <?= $revLabels ?>;
  const _revData    = <?= $revData ?>;
  const _eggLabels  = <?= $eggLabels ?>;
  const _eggQty     = <?= $eggQty ?>;
  const _eggTgt     = <?= $eggTgt ?>;
  const _typeLabels = <?= $typeLabels ?>;
  const _typeData   = <?= $typeData ?>;

  let _revInst, _typeInst, _eggInst;

  function buildDashCharts() {
    const dark    = document.documentElement.dataset.theme === 'dark';
    const textCol = dark ? 'rgba(200,230,205,0.5)'  : 'rgba(26,46,26,0.45)';
    const gridCol = dark ? 'rgba(100,180,130,0.07)' : 'rgba(39,97,64,0.06)';
    const tipBg   = dark ? 'rgba(8,18,12,0.96)'     : 'rgba(4,12,8,0.93)';
    const borderBg= dark ? 'rgba(8,18,12,0.9)'      : 'rgba(255,255,255,0.9)';

    const font       = { family: "'Inter', system-ui, sans-serif", size: 11 };
    const tooltipCfg = {
      backgroundColor: tipBg,
      titleColor: '#c8e6cd',
      bodyColor:  'rgba(200,230,205,0.7)',
      borderColor:'rgba(100,180,130,0.2)',
      borderWidth: 1, padding: 12, cornerRadius: 10,
      titleFont: { ...font, weight: '600', size: 12 }, bodyFont: font,
    };
    const scalesMoney = {
      x: { ticks:{color:textCol,font,maxRotation:0,padding:6}, grid:{color:gridCol,drawTicks:false}, border:{display:false} },
      y: { ticks:{color:textCol,font,padding:6,callback:v=>'₦'+Intl.NumberFormat('en-NG',{notation:'compact'}).format(v)}, grid:{color:gridCol}, border:{display:false}, beginAtZero:true }
    };
    const scalesCount = {
      x: { ticks:{color:textCol,font,maxRotation:0,padding:6}, grid:{color:gridCol,drawTicks:false}, border:{display:false} },
      y: { ticks:{color:textCol,font,padding:6}, grid:{color:gridCol}, border:{display:false}, beginAtZero:true }
    };

    _revInst?.destroy();
    _typeInst?.destroy();
    _eggInst?.destroy();

    // ── Revenue Bar ──
    const revEl = document.getElementById('revChart');
    if (revEl) {
      const gBar = revEl.getContext('2d').createLinearGradient(0, 0, 0, 260);
      gBar.addColorStop(0, 'rgba(45,106,79,0.85)');
      gBar.addColorStop(1, 'rgba(45,106,79,0.30)');
      _revInst = new Chart(revEl, {
        type: 'bar',
        data: {
          labels: _revLabels,
          datasets: [{ label:'Revenue', data:_revData, backgroundColor:gBar, borderRadius:7, borderSkipped:false, hoverBackgroundColor:'rgba(64,145,108,0.9)' }]
        },
        options: {
          responsive:true, maintainAspectRatio:false, animation:{duration:700},
          plugins: { legend:{display:false}, tooltip:{...tooltipCfg, callbacks:{label:c=>'  ₦'+Number(c.raw).toLocaleString('en-NG')}} },
          scales: scalesMoney,
        }
      });
    }

    // ── Revenue Donut ──
    const typeEl = document.getElementById('typeChart');
    if (typeEl) {
      const palette = dark
        ? ['rgba(64,145,108,0.85)','rgba(196,125,14,0.85)','rgba(96,165,250,0.85)','rgba(167,139,250,0.85)']
        : ['rgba(30,90,56,0.85)',  'rgba(160,100,10,0.85)','rgba(37,99,235,0.85)', 'rgba(109,40,217,0.85)'];
      _typeInst = new Chart(typeEl, {
        type:'doughnut',
        data: {
          labels: _typeLabels.map(l=>l.charAt(0).toUpperCase()+l.slice(1)),
          datasets: [{ data:_typeData, backgroundColor:palette, borderColor:borderBg, borderWidth:3, hoverOffset:8 }]
        },
        options: {
          responsive:true, maintainAspectRatio:false, cutout:'68%', animation:{duration:700},
          plugins: { legend:{display:false}, tooltip:{...tooltipCfg, callbacks:{label:c=>' ₦'+Number(c.raw).toLocaleString('en-NG')}} }
        }
      });
    }

    // ── Egg Line ──
    const eggEl = document.getElementById('eggChart');
    if (eggEl) {
      const gEgg = eggEl.getContext('2d').createLinearGradient(0, 0, 0, 220);
      gEgg.addColorStop(0, dark ? 'rgba(224,148,32,0.22)' : 'rgba(192,125,10,0.16)');
      gEgg.addColorStop(1, 'rgba(192,125,10,0.01)');
      _eggInst = new Chart(eggEl, {
        type:'line',
        data: {
          labels: _eggLabels,
          datasets: [
            { label:'Eggs Collected', data:_eggQty, borderColor:dark?'rgba(224,148,32,0.9)':'rgba(192,125,10,0.9)', backgroundColor:gEgg, fill:true, tension:0.42, borderWidth:2.5, pointRadius:4, pointHoverRadius:6, pointBackgroundColor:dark?'#e09420':'#c47d0e', pointBorderColor:dark?'#0c1810':'#fff', pointBorderWidth:2 },
            { label:'Daily Target',   data:_eggTgt, borderColor:dark?'rgba(96,165,250,0.55)':'rgba(37,99,235,0.38)', backgroundColor:'transparent', borderDash:[6,4], pointRadius:0, tension:0, borderWidth:1.5 }
          ]
        },
        options: {
          responsive:true, maintainAspectRatio:false, animation:{duration:700},
          plugins: { legend:{display:false}, tooltip:{...tooltipCfg, mode:'index', intersect:false, callbacks:{label:c=>' '+c.dataset.label+': '+Number(c.raw).toLocaleString()+' eggs'}} },
          scales: scalesCount,
          interaction:{mode:'nearest',axis:'x',intersect:false},
        }
      });
    }
  }

  // Build immediately (Chart.js is already loaded above this script tag)
  buildDashCharts();

  // Rebuild on theme change
  document.addEventListener('themechange', () => setTimeout(buildDashCharts, 80));
})();
</script>

<?php include __DIR__ . '/templates/footer.php'; ?>