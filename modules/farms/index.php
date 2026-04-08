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

// Summary stats
$totalRevenue  = array_sum(array_column($farms, 'total_revenue'));
$totalAnimals  = array_sum(array_column($farms, 'animal_count'));
$totalCrops    = array_sum(array_column($farms, 'crop_count'));

$pageTitle = 'Farms';
$activeNav = 'farms';
include __DIR__ . '/../../templates/header.php';
?>

<style>
/* ── Page-scoped styles ───────────────────────────────────── */
.farms-hero {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 28px;
  flex-wrap: wrap;
}
.farms-hero-left h2 {
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -.6px;
  color: var(--text-primary);
  line-height: 1.1;
}
.farms-hero-left p {
  font-size: 13.5px;
  color: var(--text-muted);
  margin-top: 5px;
}

/* Summary strip */
.farms-summary {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
  margin-bottom: 28px;
}
.summary-tile {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 18px 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: var(--shadow-card);
  transition: box-shadow .2s, transform .2s;
}
.summary-tile:hover {
  box-shadow: var(--shadow-card-hover);
  transform: translateY(-1px);
}
.summary-tile-icon {
  width: 46px;
  height: 46px;
  border-radius: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.summary-tile-icon svg {
  width: 20px;
  height: 20px;
  fill: none;
  stroke-width: 1.75;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.summary-tile-icon.emerald { background: rgba(45,106,79,.1); }
.summary-tile-icon.emerald svg { stroke: #2d6a4f; }
.summary-tile-icon.azure   { background: rgba(37,99,235,.1); }
.summary-tile-icon.azure svg   { stroke: #2563eb; }
.summary-tile-icon.amber   { background: rgba(192,125,10,.1); }
.summary-tile-icon.amber svg   { stroke: #c07d0a; }
.summary-tile-value {
  font-size: 22px;
  font-weight: 800;
  font-family: var(--ff-mono);
  color: var(--text-primary);
  line-height: 1;
  letter-spacing: -.5px;
}
.summary-tile-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: .55px;
  margin-top: 3px;
}

/* Farm cards grid */
.farms-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 18px;
}
@media (max-width: 1100px) { .farms-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 640px)  { .farms-grid, .farms-summary { grid-template-columns: 1fr; } }

/* Farm card */
.farm-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 14px;
  box-shadow: var(--shadow-card);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: box-shadow .22s, transform .22s, border-color .22s;
  position: relative;
}
.farm-card:hover {
  box-shadow: var(--shadow-card-hover);
  transform: translateY(-3px);
  border-color: var(--border-input);
}

/* Accent bar at top — colour per farm type */
.farm-card-accent {
  height: 3px;
  width: 100%;
}
.accent-crop       { background: linear-gradient(90deg, #2d6a4f, #52b788); }
.accent-livestock  { background: linear-gradient(90deg, #92400e, #d97706); }
.accent-mixed      { background: linear-gradient(90deg, #1e40af, #3b82f6); }
.accent-poultry    { background: linear-gradient(90deg, #b45309, #fbbf24); }
.accent-aquaculture{ background: linear-gradient(90deg, #0e7490, #22d3ee); }
.accent-orchard    { background: linear-gradient(90deg, #166534, #86efac); }
.accent-default    { background: linear-gradient(90deg, #374151, #9ca3af); }

.farm-card-body {
  padding: 20px;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0;
}

/* Header row */
.farm-card-header-row {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  margin-bottom: 16px;
}
.farm-type-icon {
  width: 44px;
  height: 44px;
  border-radius: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.farm-type-icon svg {
  width: 20px;
  height: 20px;
  fill: none;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.icon-crop        { background: rgba(45,106,79,.1);   } .icon-crop svg        { stroke: #2d6a4f; }
.icon-livestock   { background: rgba(180,83,9,.1);    } .icon-livestock svg   { stroke: #b45309; }
.icon-mixed       { background: rgba(30,64,175,.1);   } .icon-mixed svg       { stroke: #1e40af; }
.icon-poultry     { background: rgba(217,119,6,.1);   } .icon-poultry svg     { stroke: #d97706; }
.icon-aquaculture { background: rgba(14,116,144,.1);  } .icon-aquaculture svg { stroke: #0e7490; }
.icon-orchard     { background: rgba(22,101,52,.1);   } .icon-orchard svg     { stroke: #166534; }
.icon-default     { background: rgba(55,65,81,.1);    } .icon-default svg     { stroke: #374151; }

.farm-name {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-primary);
  letter-spacing: -.2px;
  line-height: 1.2;
}
.farm-location {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--text-muted);
  margin-top: 3px;
}
.farm-location svg {
  width: 11px;
  height: 11px;
  stroke: currentColor;
  fill: none;
  stroke-width: 2;
  stroke-linecap: round;
  flex-shrink: 0;
}
.farm-type-badge {
  margin-left: auto;
  flex-shrink: 0;
  font-size: 10.5px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 20px;
  text-transform: uppercase;
  letter-spacing: .5px;
  border: 1px solid;
}
.badge-crop        { background: rgba(45,106,79,.08);  color: #2d6a4f;  border-color: rgba(45,106,79,.2);  }
.badge-livestock   { background: rgba(180,83,9,.08);   color: #b45309;  border-color: rgba(180,83,9,.2);   }
.badge-mixed       { background: rgba(30,64,175,.08);  color: #1e40af;  border-color: rgba(30,64,175,.2);  }
.badge-poultry     { background: rgba(217,119,6,.08);  color: #d97706;  border-color: rgba(217,119,6,.2);  }
.badge-aquaculture { background: rgba(14,116,144,.08); color: #0e7490;  border-color: rgba(14,116,144,.2); }
.badge-orchard     { background: rgba(22,101,52,.08);  color: #166534;  border-color: rgba(22,101,52,.2);  }
.badge-default     { background: rgba(55,65,81,.08);   color: #374151;  border-color: rgba(55,65,81,.2);   }
[data-theme="dark"] .badge-crop        { color:#52b788; border-color:rgba(82,183,136,.25); background:rgba(82,183,136,.08); }
[data-theme="dark"] .badge-livestock   { color:#fbbf24; border-color:rgba(251,191,36,.25); background:rgba(251,191,36,.08); }
[data-theme="dark"] .badge-mixed       { color:#93c5fd; border-color:rgba(147,197,253,.25);background:rgba(147,197,253,.08);}
[data-theme="dark"] .badge-poultry     { color:#fcd34d; border-color:rgba(252,211,77,.25); background:rgba(252,211,77,.08); }
[data-theme="dark"] .badge-aquaculture { color:#67e8f9; border-color:rgba(103,232,249,.25);background:rgba(103,232,249,.08);}
[data-theme="dark"] .badge-orchard     { color:#86efac; border-color:rgba(134,239,172,.25);background:rgba(134,239,172,.08);}

/* Divider */
.farm-divider {
  height: 1px;
  background: var(--border-color);
  margin: 0 0 16px;
}

/* Stats row */
.farm-stats-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 16px;
}
.farm-stat-cell {
  background: var(--bg-body);
  border: 1px solid var(--border-color);
  border-radius: 9px;
  padding: 11px 14px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.farm-stat-cell svg {
  width: 14px;
  height: 14px;
  fill: none;
  stroke: var(--text-muted);
  stroke-width: 1.75;
  stroke-linecap: round;
  stroke-linejoin: round;
  flex-shrink: 0;
}
.farm-stat-num  { font-size: 17px; font-weight: 800; font-family: var(--ff-mono); color: var(--text-primary); line-height: 1; }
.farm-stat-lbl  { font-size: 10.5px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .4px; margin-top: 2px; }

/* Revenue row */
.farm-revenue-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--bg-body);
  border: 1px solid var(--border-color);
  border-radius: 9px;
  padding: 11px 14px;
  margin-bottom: 16px;
}
.farm-revenue-label {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 11px;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: .45px;
}
.farm-revenue-label svg {
  width: 13px;
  height: 13px;
  fill: none;
  stroke: var(--text-muted);
  stroke-width: 1.75;
  stroke-linecap: round;
  flex-shrink: 0;
}
.farm-revenue-value {
  font-size: 16px;
  font-weight: 800;
  font-family: var(--ff-mono);
  color: var(--color-brand);
  letter-spacing: -.3px;
}
[data-theme="dark"] .farm-revenue-value { color: #52b788; }

/* Meta row */
.farm-meta-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  color: var(--text-muted);
  margin-bottom: 16px;
}
.farm-meta-row svg {
  width: 12px;
  height: 12px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.75;
  stroke-linecap: round;
  flex-shrink: 0;
}
.farm-meta-dot { width: 3px; height: 3px; border-radius: 50%; background: var(--border-input); flex-shrink: 0; }

/* Actions */
.farm-card-actions {
  display: flex;
  gap: 8px;
  margin-top: auto;
  padding-top: 4px;
}
.farm-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 600;
  border: 1px solid var(--border-color);
  background: transparent;
  color: var(--text-secondary);
  cursor: pointer;
  text-decoration: none;
  transition: all .18s;
}
.farm-action-btn svg {
  width: 13px;
  height: 13px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  flex-shrink: 0;
}
.farm-action-btn:hover {
  border-color: var(--color-brand-light);
  color: var(--color-brand);
  background: rgba(45,106,79,.05);
}
.farm-action-btn.danger:hover {
  border-color: #c53030;
  color: #c53030;
  background: rgba(197,48,48,.05);
}
.farm-action-btn.danger { margin-left: auto; }

/* Empty state */
.farms-empty {
  grid-column: 1 / -1;
  text-align: center;
  padding: 80px 40px;
  color: var(--text-muted);
}
.farms-empty-icon {
  width: 64px;
  height: 64px;
  border-radius: 16px;
  background: var(--bg-body);
  border: 1px solid var(--border-color);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20px;
}
.farms-empty-icon svg {
  width: 28px;
  height: 28px;
  fill: none;
  stroke: var(--text-muted);
  stroke-width: 1.5;
  stroke-linecap: round;
}
.farms-empty h3 { font-size: 16px; font-weight: 600; color: var(--text-secondary); margin-bottom: 8px; }
.farms-empty p  { font-size: 13px; margin-bottom: 20px; }
</style>

<!-- ── Page Header ─────────────────────────────────────────── -->
<div class="farms-hero">
  <div class="farms-hero-left">
    <h2>Farm Locations</h2>
    <p><?= count($farms) ?> registered farm<?= count($farms) !== 1 ? 's' : '' ?> across your operation</p>
  </div>
  <?php if (RBAC::can('farms', 'create')): ?>
  <a href="create.php" class="ff-btn ff-btn-primary">
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Add Farm
  </a>
  <?php endif; ?>
</div>

<!-- ── Summary Tiles ──────────────────────────────────────── -->
<?php if (!empty($farms)): ?>
<div class="farms-summary">
  <div class="summary-tile">
    <div class="summary-tile-icon emerald">
      <svg viewBox="0 0 24 24"><path d="M12 2C8 2 4 6 4 10c0 5.25 8 12 8 12s8-6.75 8-12c0-4-4-8-8-8z"/><circle cx="12" cy="10" r="2.5"/></svg>
    </div>
    <div>
      <div class="summary-tile-value"><?= count($farms) ?></div>
      <div class="summary-tile-label">Total Farms</div>
    </div>
  </div>
  <div class="summary-tile">
    <div class="summary-tile-icon azure">
      <svg viewBox="0 0 24 24"><path d="M3 10c0-2.5 1.5-4 4-4 .5 0 1 .1 1.5.3M21 10c0-2.5-1.5-4-4-4-.5 0-1 .1-1.5.3M8.5 6.3C9 5 10 4 12 4s3 1 3.5 2.3M6 14c0 3.3 2.7 6 6 6s6-2.7 6-6"/></svg>
    </div>
    <div>
      <div class="summary-tile-value"><?= number_format($totalAnimals) ?></div>
      <div class="summary-tile-label">Active Animals</div>
    </div>
  </div>
  <div class="summary-tile">
    <div class="summary-tile-icon amber">
      <svg viewBox="0 0 24 24"><path d="M12 2C6 2 3 7 3 11c1 5 4 8 9 9 5-1 8-4 9-9 0-4-3-9-9-9z"/><path d="M12 11v7"/></svg>
    </div>
    <div>
      <div class="summary-tile-value"><?= money($totalRevenue) ?></div>
      <div class="summary-tile-label">Total Revenue</div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── Farms Grid ─────────────────────────────────────────── -->
<div class="farms-grid">
<?php
// Icon SVG paths per farm type
$typeIconMap = [
  'crop' => '<path d="M12 22V12M12 12C12 7 8 4 3 4c0 5 3 9 9 9M12 12c0-5 4-8 9-8-1 5-4 8-9 8"/>',
  'livestock' => '<path d="M3 10c0-2.5 1.5-4 4-4 .5 0 1 .1 1.5.3M21 10c0-2.5-1.5-4-4-4-.5 0-1 .1-1.5.3M8.5 6.3C9 5 10 4 12 4s3 1 3.5 2.3M6 14c0 3.3 2.7 6 6 6s6-2.7 6-6"/>',
  'mixed' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
  'poultry' => '<path d="M19 8a3 3 0 01-3 3H8a3 3 0 010-6c.34 0 .67.03 1 .1A4.5 4.5 0 0116 3c1.5 0 2.8.7 3.6 1.8"/><path d="M10 11v9M14 11v9M8 20h8"/>',
  'aquaculture' => '<path d="M2 12c2-4 6-4 8 0s6 4 8 0"/><path d="M2 8c2-4 6-4 8 0s6 4 8 0"/><path d="M2 16c2-4 6-4 8 0s6 4 8 0"/>',
  'orchard' => '<circle cx="12" cy="8" r="5"/><path d="M12 13v8M8 21h8"/>',
];
$accentMap = [
  'crop'=>'accent-crop','livestock'=>'accent-livestock','mixed'=>'accent-mixed',
  'poultry'=>'accent-poultry','aquaculture'=>'accent-aquaculture','orchard'=>'accent-orchard',
];
$iconClassMap = [
  'crop'=>'icon-crop','livestock'=>'icon-livestock','mixed'=>'icon-mixed',
  'poultry'=>'icon-poultry','aquaculture'=>'icon-aquaculture','orchard'=>'icon-orchard',
];
$badgeClassMap = [
  'crop'=>'badge-crop','livestock'=>'badge-livestock','mixed'=>'badge-mixed',
  'poultry'=>'badge-poultry','aquaculture'=>'badge-aquaculture','orchard'=>'badge-orchard',
];

foreach ($farms as $farm):
  $type       = $farm['type'] ?? 'default';
  $accentCls  = $accentMap[$type]    ?? 'accent-default';
  $iconCls    = $iconClassMap[$type] ?? 'icon-default';
  $badgeCls   = $badgeClassMap[$type]?? 'badge-default';
  $iconSvg    = $typeIconMap[$type]  ?? '<path d="M3 9.5L12 3l9 6.5V21a1 1 0 01-1 1H4a1 1 0 01-1-1V9.5z"/><path d="M9 21V12h6v9"/>';
?>
<div class="farm-card">
  <!-- Accent bar -->
  <div class="farm-card-accent <?= $accentCls ?>"></div>

  <div class="farm-card-body">
    <!-- Header row -->
    <div class="farm-card-header-row">
      <div class="farm-type-icon <?= $iconCls ?>">
        <svg fill="none" viewBox="0 0 24 24"><?= $iconSvg ?></svg>
      </div>
      <div style="flex:1;min-width:0;">
        <div class="farm-name"><?= e($farm['name']) ?></div>
        <div class="farm-location">
          <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
          <?= e($farm['city']) ?>, <?= e($farm['state']) ?>
        </div>
      </div>
      <span class="farm-type-badge <?= $badgeCls ?>"><?= e(ucfirst($type)) ?></span>
    </div>

    <div class="farm-divider"></div>

    <!-- Stats -->
    <div class="farm-stats-row">
      <div class="farm-stat-cell">
        <svg viewBox="0 0 24 24"><path d="M3 10c0-2.5 1.5-4 4-4 .5 0 1 .1 1.5.3M21 10c0-2.5-1.5-4-4-4-.5 0-1 .1-1.5.3M8.5 6.3C9 5 10 4 12 4s3 1 3.5 2.3M6 14c0 3.3 2.7 6 6 6s6-2.7 6-6"/></svg>
        <div>
          <div class="farm-stat-num"><?= number_format($farm['animal_count']) ?></div>
          <div class="farm-stat-lbl">Animals</div>
        </div>
      </div>
      <div class="farm-stat-cell">
        <svg viewBox="0 0 24 24"><path d="M12 22V12M12 12C12 7 8 4 3 4c0 5 3 9 9 9M12 12c0-5 4-8 9-8-1 5-4 8-9 8"/></svg>
        <div>
          <div class="farm-stat-num"><?= number_format($farm['crop_count']) ?></div>
          <div class="farm-stat-lbl">Crops</div>
        </div>
      </div>
    </div>

    <!-- Revenue -->
    <div class="farm-revenue-row">
      <div class="farm-revenue-label">
        <svg viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        Total Revenue
      </div>
      <div class="farm-revenue-value"><?= money($farm['total_revenue']) ?></div>
    </div>

    <!-- Meta -->
    <div class="farm-meta-row">
      <svg viewBox="0 0 24 24"><path d="M21 3H3v18l4-4h14V3z"/></svg>
      <?= number_format($farm['size'], 2) ?> ha
      <span class="farm-meta-dot"></span>
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Added <?= date('d M Y', strtotime($farm['created_at'])) ?>
    </div>

    <!-- Actions -->
    <div class="farm-card-actions">
      <?php if (RBAC::can('farms', 'edit')): ?>
      <a href="edit.php?id=<?= $farm['id'] ?>" class="farm-action-btn">
        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Edit
      </a>
      <?php endif; ?>
      <?php if (RBAC::can('farms', 'delete')): ?>
      <form method="POST" action="delete.php" id="del-f-<?= $farm['id'] ?>" style="display:contents;">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="id" value="<?= $farm['id'] ?>">
        <button type="button" class="farm-action-btn danger"
          onclick="confirmDelete('del-f-<?= $farm['id'] ?>','Delete this farm and ALL its data? This cannot be undone.')">
          <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
          Delete
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php if (empty($farms)): ?>
<div class="farms-empty">
  <div class="farms-empty-icon">
    <svg viewBox="0 0 24 24"><path d="M3 9.5L12 3l9 6.5V21a1 1 0 01-1 1H4a1 1 0 01-1-1V9.5z"/><path d="M9 21V12h6v9"/></svg>
  </div>
  <h3>No farms registered yet</h3>
  <p>Get started by adding your first farm location.</p>
  <?php if (RBAC::can('farms', 'create')): ?>
  <a href="create.php" class="ff-btn ff-btn-primary">
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Add First Farm
  </a>
  <?php endif; ?>
</div>
<?php endif; ?>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>