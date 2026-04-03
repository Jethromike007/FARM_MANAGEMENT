<?php
// modules/eggs/edit.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';

Auth::start();
Auth::require();
RBAC::require('eggs', 'edit');

$id  = (int)($_GET['id'] ?? 0);
$rec = DB::row("SELECT * FROM egg_production WHERE id = ?", [$id]);
if (!$rec) { flash('error', 'Record not found.'); redirect(APP_URL . '/modules/eggs/index.php'); }
if (RBAC::farmScope() && $rec['farm_id'] != RBAC::farmScope()) {
    flash('error', 'Access denied.'); redirect(APP_URL . '/modules/eggs/index.php');
}

$errors = [];
$farms   = DB::rows("SELECT id, name FROM farms ORDER BY name");
$animals = DB::rows("SELECT id, type, breed, farm_id FROM animals WHERE type IN ('Chicken','Duck','Turkey','Goose','Quail') AND sold = 0 ORDER BY type");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf()) { $errors[] = 'Invalid CSRF token.'; }

    $farm_id      = (int)($_POST['farm_id'] ?? 0);
    $animal_id    = (int)($_POST['animal_id'] ?? 0) ?: null;
    $date_produced= $_POST['date_produced'] ?? '';
    $quantity     = (int)($_POST['quantity'] ?? 0);
    $daily_target = (int)($_POST['daily_target'] ?? 0) ?: null;
    $sold         = isset($_POST['sold']) ? 1 : 0;
    $price_sold   = $sold ? (float)($_POST['price_sold'] ?? 0) : null;
    $notes        = trim($_POST['notes'] ?? '');

    if ($quantity < 1) $errors[] = 'Quantity must be at least 1.';

    if (empty($errors)) {
        DB::execute(
            "UPDATE egg_production SET farm_id=?, animal_id=?, date_produced=?, quantity=?, daily_target=?, sold=?, price_sold=?, notes=? WHERE id=?",
            [$farm_id, $animal_id, $date_produced, $quantity, $daily_target, $sold, $price_sold, $notes, $id]
        );
        Logger::log(Auth::id(), 'update', 'egg_production', $id, "Updated egg record #{$id}: {$quantity} eggs");
        flash('success', 'Record updated.');
        redirect(APP_URL . '/modules/eggs/index.php');
    }
    $rec = array_merge($rec, $_POST);
}

$pageTitle = 'Edit Egg Record';
$activeNav = 'eggs';
include __DIR__ . '/../../templates/header.php';
?>

<div class="ff-page-header">
  <div><h2 class="ff-page-title">Edit Egg Record</h2></div>
  <a href="index.php" class="ff-btn ff-btn-outline"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php foreach ($errors as $err): ?>
<div class="ff-alert ff-alert-error"><i class="bi bi-x-circle-fill"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<div class="ff-card" style="max-width:600px;">
  <div class="ff-card-header"><span class="ff-card-title">Edit Record #<?= $id ?></span></div>
  <div class="ff-card-body">
    <form method="POST">
      <?= Auth::csrfField() ?>
      <div class="grid-2" style="gap:18px;">
        <div>
          <label class="ff-form-label">Farm *</label>
          <select name="farm_id" class="ff-form-control" required>
            <?php foreach ($farms as $f): ?>
            <?php if (RBAC::farmScope() && $f['id'] != RBAC::farmScope()) continue; ?>
            <option value="<?= $f['id'] ?>" <?= $rec['farm_id'] == $f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="ff-form-label">Flock / Animal</label>
          <select name="animal_id" class="ff-form-control">
            <option value="">Not specified</option>
            <?php foreach ($animals as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $rec['animal_id'] == $a['id'] ? 'selected' : '' ?>><?= e($a['type']) ?><?= $a['breed'] ? ' — ' . e($a['breed']) : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="ff-form-label">Date Produced *</label>
          <input type="date" name="date_produced" class="ff-form-control" required value="<?= e($rec['date_produced']) ?>">
        </div>
        <div>
          <label class="ff-form-label">Quantity *</label>
          <input type="number" name="quantity" class="ff-form-control" min="1" required value="<?= e($rec['quantity']) ?>">
        </div>
        <div>
          <label class="ff-form-label">Daily Target</label>
          <input type="number" name="daily_target" class="ff-form-control" value="<?= e($rec['daily_target'] ?? '') ?>">
        </div>
        <div style="display:flex;flex-direction:column;justify-content:flex-end;">
          <label class="ff-form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="sold" id="soldToggle" <?= $rec['sold'] ? 'checked' : '' ?>
                   onchange="document.getElementById('priceRow').style.display=this.checked?'block':'none'"
                   style="width:16px;height:16px;">
            Mark as Sold
          </label>
        </div>
      </div>
      <div id="priceRow" style="margin-top:16px;<?= $rec['sold'] ? '' : 'display:none;' ?>">
        <label class="ff-form-label">Sale Revenue (₦)</label>
        <input type="number" name="price_sold" class="ff-form-control" step="0.01" value="<?= e($rec['price_sold'] ?? '') ?>">
      </div>
      <div class="mt-16">
        <label class="ff-form-label">Notes</label>
        <textarea name="notes" class="ff-form-control"><?= e($rec['notes'] ?? '') ?></textarea>
      </div>
      <div class="d-flex gap-12 mt-16">
        <button type="submit" class="ff-btn ff-btn-primary"><i class="bi bi-check-circle"></i> Update</button>
        <a href="index.php" class="ff-btn ff-btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
