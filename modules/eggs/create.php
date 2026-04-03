<?php
// modules/eggs/create.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';
require_once __DIR__ . '/../../config/mailer.php';

Auth::start();
Auth::require();
RBAC::require('eggs', 'create');

$errors = [];
$farms  = DB::rows("SELECT id, name FROM farms ORDER BY name");

// Poultry animals only
$scope = RBAC::farmScope();
$animalWhere = $scope ? "AND farm_id = $scope" : '';
$animals = DB::rows("SELECT id, type, breed, farm_id FROM animals WHERE type IN ('Chicken','Duck','Turkey','Goose','Quail') AND sold = 0 $animalWhere ORDER BY type");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf()) { $errors[] = 'Invalid CSRF token.'; }

    $farm_id      = (int)($_POST['farm_id'] ?? 0);
    $animal_id    = (int)($_POST['animal_id'] ?? 0) ?: null;
    $date_produced= $_POST['date_produced'] ?? date('Y-m-d');
    $quantity     = (int)($_POST['quantity'] ?? 0);
    $daily_target = (int)($_POST['daily_target'] ?? 0) ?: null;
    $sold         = isset($_POST['sold']) ? 1 : 0;
    $price_sold   = $sold ? (float)($_POST['price_sold'] ?? 0) : null;
    $notes        = trim($_POST['notes'] ?? '');

    if (!$farm_id)     $errors[] = 'Farm is required.';
    if ($quantity < 1) $errors[] = 'Quantity must be at least 1.';
    if (!$date_produced) $errors[] = 'Date is required.';
    if ($sold && !$price_sold) $errors[] = 'Price is required if eggs are sold.';

    if ($scope && $farm_id !== $scope) $errors[] = 'Access denied to that farm.';

    if (empty($errors)) {
        $id = DB::insert(
            "INSERT INTO egg_production (farm_id, animal_id, date_produced, quantity, daily_target, sold, price_sold, notes, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$farm_id, $animal_id, $date_produced, $quantity, $daily_target, $sold, $price_sold, $notes, Auth::id()]
        );

        // If sold, add to sales ledger
        if ($sold && $price_sold > 0) {
            DB::insert(
                "INSERT INTO sales (farm_id, entity_type, entity_id, sale_date, quantity, unit_price, recorded_by)
                 VALUES (?, 'egg', ?, ?, ?, ?, ?)",
                [$farm_id, $id, $date_produced, $quantity, round($price_sold / $quantity, 4), Auth::id()]
            );
        }

        Logger::log(Auth::id(), 'record_eggs', 'egg_production', $id, "Recorded {$quantity} eggs for farm #{$farm_id} on {$date_produced}");

        $user = Auth::user();
        if ($_SESSION['email_notifications']) {
            Mailer::send($user['email'], $user['name'], 'Egg Production Recorded — ' . APP_NAME,
                "<p>{$quantity} eggs recorded for farm #{$farm_id} on {$date_produced}." . ($daily_target ? " Target: {$daily_target}." : '') . "</p>");
        }

        flash('success', "{$quantity} eggs recorded successfully.");
        redirect(APP_URL . '/modules/eggs/index.php');
    }
}

$pageTitle = 'Record Egg Production';
$activeNav = 'eggs';
include __DIR__ . '/../../templates/header.php';
?>

<div class="ff-page-header">
  <div><h2 class="ff-page-title">🥚 Record Egg Production</h2></div>
  <a href="index.php" class="ff-btn ff-btn-outline"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php foreach ($errors as $err): ?>
<div class="ff-alert ff-alert-error"><i class="bi bi-x-circle-fill"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<div class="ff-card" style="max-width:600px;">
  <div class="ff-card-header"><span class="ff-card-title">Daily Entry</span></div>
  <div class="ff-card-body">
    <form method="POST" id="eggForm">
      <?= Auth::csrfField() ?>
      <div class="grid-2" style="gap:18px;">

        <div>
          <label class="ff-form-label">Farm *</label>
          <select name="farm_id" class="ff-form-control" required>
            <option value="">Select farm…</option>
            <?php foreach ($farms as $f): ?>
            <?php if ($scope && $f['id'] != $scope) continue; ?>
            <option value="<?= $f['id'] ?>" <?= (($_POST['farm_id'] ?? '') == $f['id']) ? 'selected' : '' ?>><?= e($f['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="ff-form-label">Flock / Animal <span style="color:var(--text-muted);font-weight:400;">(optional)</span></label>
          <select name="animal_id" class="ff-form-control">
            <option value="">Not specified</option>
            <?php foreach ($animals as $a): ?>
            <option value="<?= $a['id'] ?>" <?= (($_POST['animal_id'] ?? '') == $a['id']) ? 'selected' : '' ?>>
              <?= e($a['type']) ?><?= $a['breed'] ? ' — ' . e($a['breed']) : '' ?> (Farm #<?= $a['farm_id'] ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="ff-form-label">Date Produced *</label>
          <input type="date" name="date_produced" class="ff-form-control" required value="<?= e($_POST['date_produced'] ?? date('Y-m-d')) ?>">
        </div>

        <div>
          <label class="ff-form-label">Eggs Collected *</label>
          <input type="number" name="quantity" class="ff-form-control" min="1" required placeholder="e.g. 200" value="<?= e($_POST['quantity'] ?? '') ?>">
        </div>

        <div>
          <label class="ff-form-label">Daily Target <span style="color:var(--text-muted);font-weight:400;">(optional)</span></label>
          <input type="number" name="daily_target" class="ff-form-control" min="1" placeholder="e.g. 220" value="<?= e($_POST['daily_target'] ?? '') ?>">
        </div>

        <div style="display:flex;flex-direction:column;justify-content:flex-end;">
          <label class="ff-form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="sold" id="soldToggle" <?= isset($_POST['sold']) ? 'checked' : '' ?> 
                   onchange="document.getElementById('priceRow').style.display=this.checked?'block':'none'"
                   style="width:16px;height:16px;">
            Mark as Sold
          </label>
        </div>

      </div>

      <div id="priceRow" style="margin-top:16px;<?= isset($_POST['sold']) ? '' : 'display:none;' ?>">
        <label class="ff-form-label">Sale Revenue (₦) *</label>
        <input type="number" name="price_sold" class="ff-form-control" step="0.01" min="0" placeholder="Total amount received" value="<?= e($_POST['price_sold'] ?? '') ?>">
      </div>

      <div class="mt-16">
        <label class="ff-form-label">Notes</label>
        <textarea name="notes" class="ff-form-control" placeholder="e.g. Production low due to heat stress…"><?= e($_POST['notes'] ?? '') ?></textarea>
      </div>

      <div class="d-flex gap-12 mt-16">
        <button type="submit" class="ff-btn ff-btn-primary"><i class="bi bi-check-circle"></i> Save Record</button>
        <a href="index.php" class="ff-btn ff-btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
