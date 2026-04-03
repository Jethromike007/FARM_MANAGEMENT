<?php
// modules/crops/create.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';
require_once __DIR__ . '/../../config/mailer.php';

Auth::start();
Auth::require();
RBAC::require('crops', 'create');

$errors = [];
$farms  = DB::rows("SELECT id, name FROM farms ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf()) { $errors[] = 'Invalid CSRF token.'; }

    $farm_id      = (int)($_POST['farm_id'] ?? 0);
    $type         = trim($_POST['type'] ?? '');
    $variety      = trim($_POST['variety'] ?? '');
    $quantity     = (float)($_POST['quantity'] ?? 0);
    $qty_unit     = $_POST['quantity_unit'] ?? 'kg';
    $planting_date= $_POST['planting_date'] ?? '';
    $maturity_days= (int)($_POST['maturity_days'] ?? 0);
    $notes        = trim($_POST['notes'] ?? '');

    if (!$farm_id)         $errors[] = 'Farm is required.';
    if (!$type)            $errors[] = 'Crop type is required.';
    if ($quantity <= 0)    $errors[] = 'Quantity must be > 0.';
    if (!$planting_date)   $errors[] = 'Planting date is required.';
    if ($maturity_days < 1)$errors[] = 'Maturity days must be at least 1.';

    if (RBAC::farmScope() && $farm_id !== RBAC::farmScope()) {
        $errors[] = 'You cannot add crops to that farm.';
    }

    if (empty($errors)) {
        $id = DB::insert(
            "INSERT INTO crops (farm_id, type, variety, quantity, quantity_unit, planting_date, maturity_days, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$farm_id, $type, $variety, $quantity, $qty_unit, $planting_date, $maturity_days, $notes]
        );
        Logger::log(Auth::id(), 'create', 'crops', $id, "Added crop: {$quantity}{$qty_unit} of {$type}");
        $user = Auth::user();
        if ($_SESSION['email_notifications']) {
            Mailer::send($user['email'], $user['name'], 'New Crop Added — '.APP_NAME,
                "<p>{$quantity} {$qty_unit} of {$type} planted on {$planting_date}. Ready in {$maturity_days} days.</p>");
        }
        flash('success', "{$type} crop added successfully.");
        redirect(APP_URL . '/modules/crops/index.php');
    }
}

$pageTitle = 'Add Crop';
$activeNav = 'crops';
include __DIR__ . '/../../templates/header.php';
?>

<div class="ff-page-header">
  <div><h2 class="ff-page-title">Add Crop</h2></div>
  <a href="index.php" class="ff-btn ff-btn-outline"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php foreach ($errors as $e): ?>
<div class="ff-alert ff-alert-error"><i class="bi bi-x-circle-fill"></i> <?= e($e) ?></div>
<?php endforeach; ?>

<div class="ff-card" style="max-width:680px;">
  <div class="ff-card-header"><span class="ff-card-title">Crop Details</span></div>
  <div class="ff-card-body">
    <form method="POST">
      <?= Auth::csrfField() ?>
      <div class="grid-2" style="gap:18px;">
        <div>
          <label class="ff-form-label">Farm *</label>
          <select name="farm_id" class="ff-form-control" required>
            <option value="">Select farm…</option>
            <?php foreach ($farms as $f): ?>
            <?php if (RBAC::farmScope() && $f['id'] != RBAC::farmScope()) continue; ?>
            <option value="<?= $f['id'] ?>" <?= (($_POST['farm_id'] ?? '') == $f['id']) ? 'selected' : '' ?>><?= e($f['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="ff-form-label">Crop Type *</label>
          <input type="text" name="type" class="ff-form-control" placeholder="e.g. Maize, Tomato" required value="<?= e($_POST['type'] ?? '') ?>">
        </div>
        <div>
          <label class="ff-form-label">Variety</label>
          <input type="text" name="variety" class="ff-form-control" placeholder="e.g. SUWAN-1" value="<?= e($_POST['variety'] ?? '') ?>">
        </div>
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:8px;">
          <div>
            <label class="ff-form-label">Quantity *</label>
            <input type="number" name="quantity" class="ff-form-control" step="0.01" min="0.01" required value="<?= e($_POST['quantity'] ?? '') ?>">
          </div>
          <div>
            <label class="ff-form-label">Unit</label>
            <select name="quantity_unit" class="ff-form-control">
              <?php foreach (['kg','g','tonnes','units','bunches','tubers','bags'] as $u): ?>
              <option value="<?= $u ?>" <?= (($_POST['quantity_unit'] ?? 'kg') === $u) ? 'selected' : '' ?>><?= $u ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="ff-form-label">Planting Date *</label>
          <input type="date" name="planting_date" class="ff-form-control" required value="<?= e($_POST['planting_date'] ?? '') ?>">
        </div>
        <div>
          <label class="ff-form-label">Days to Maturity *</label>
          <input type="number" name="maturity_days" class="ff-form-control" min="1" required value="<?= e($_POST['maturity_days'] ?? '') ?>">
        </div>
      </div>
      <div class="mt-16">
        <label class="ff-form-label">Notes</label>
        <textarea name="notes" class="ff-form-control"><?= e($_POST['notes'] ?? '') ?></textarea>
      </div>
      <div class="d-flex gap-12 mt-16">
        <button type="submit" class="ff-btn ff-btn-primary"><i class="bi bi-check-circle"></i> Save Crop</button>
        <a href="index.php" class="ff-btn ff-btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
