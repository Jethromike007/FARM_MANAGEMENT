<?php
// modules/animals/edit.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';

Auth::start();
Auth::require();
RBAC::require('animals', 'edit');

$id     = (int)($_GET['id'] ?? 0);
$animal = DB::row("SELECT * FROM animals WHERE id = ?", [$id]);
if (!$animal) { flash('error', 'Animal not found.'); redirect(APP_URL . '/modules/animals/index.php'); }

// Farm scope check
if (RBAC::farmScope() && $animal['farm_id'] != RBAC::farmScope()) {
    flash('error', 'Access denied.'); redirect(APP_URL . '/modules/animals/index.php');
}

$errors = [];
$farms  = DB::rows("SELECT id, name FROM farms ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf()) { $errors[] = 'Invalid CSRF token.'; }

    $farm_id      = (int)($_POST['farm_id'] ?? 0);
    $type         = trim($_POST['type'] ?? '');
    $breed        = trim($_POST['breed'] ?? '');
    $quantity     = (int)($_POST['quantity'] ?? 1);
    $birth_date   = $_POST['birth_date'] ?? '';
    $maturity_days= (int)($_POST['maturity_days'] ?? 0);
    $health_status= $_POST['health_status'] ?? 'healthy';
    $notes        = trim($_POST['notes'] ?? '');

    if (!$type)           $errors[] = 'Animal type is required.';
    if ($quantity < 1)    $errors[] = 'Quantity must be at least 1.';
    if (!$birth_date)     $errors[] = 'Birth date is required.';
    if ($maturity_days < 1) $errors[] = 'Maturity days must be at least 1.';

    if (empty($errors)) {
        DB::execute(
            "UPDATE animals SET farm_id=?, type=?, breed=?, quantity=?, birth_date=?, maturity_days=?, health_status=?, notes=? WHERE id=?",
            [$farm_id, $type, $breed, $quantity, $birth_date, $maturity_days, $health_status, $notes, $id]
        );
        Logger::log(Auth::id(), 'update', 'animals', $id, "Updated animal #{$id}: {$type}");
        flash('success', 'Animal updated successfully.');
        redirect(APP_URL . '/modules/animals/index.php');
    }

    // Merge POST into $animal for re-display
    $animal = array_merge($animal, $_POST);
}

$pageTitle = 'Edit Animal';
$activeNav = 'animals';
include __DIR__ . '/../../templates/header.php';
?>

<div class="ff-page-header">
  <div>
    <h2 class="ff-page-title">Edit Animal</h2>
    <p class="ff-page-subtitle">Update animal record #<?= $id ?></p>
  </div>
  <a href="index.php" class="ff-btn ff-btn-outline"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php foreach ($errors as $err): ?>
<div class="ff-alert ff-alert-error"><i class="bi bi-x-circle-fill"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<div class="ff-card" style="max-width:680px;">
  <div class="ff-card-header"><span class="ff-card-title">Edit: <?= e($animal['type']) ?></span></div>
  <div class="ff-card-body">
    <form method="POST">
      <?= Auth::csrfField() ?>
      <div class="grid-2" style="gap:18px;">
        <div>
          <label class="ff-form-label">Farm *</label>
          <select name="farm_id" class="ff-form-control" required>
            <?php foreach ($farms as $f): ?>
            <?php if (RBAC::farmScope() && $f['id'] != RBAC::farmScope()) continue; ?>
            <option value="<?= $f['id'] ?>" <?= $animal['farm_id'] == $f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="ff-form-label">Animal Type *</label>
          <input type="text" name="type" class="ff-form-control" required value="<?= e($animal['type']) ?>">
        </div>
        <div>
          <label class="ff-form-label">Breed</label>
          <input type="text" name="breed" class="ff-form-control" value="<?= e($animal['breed'] ?? '') ?>">
        </div>
        <div>
          <label class="ff-form-label">Quantity *</label>
          <input type="number" name="quantity" class="ff-form-control" min="1" required value="<?= e($animal['quantity']) ?>">
        </div>
        <div>
          <label class="ff-form-label">Birth / Arrival Date *</label>
          <input type="date" name="birth_date" class="ff-form-control" required value="<?= e($animal['birth_date']) ?>">
        </div>
        <div>
          <label class="ff-form-label">Days to Maturity *</label>
          <input type="number" name="maturity_days" class="ff-form-control" min="1" required value="<?= e($animal['maturity_days']) ?>">
        </div>
        <div>
          <label class="ff-form-label">Health Status</label>
          <select name="health_status" class="ff-form-control">
            <?php foreach (['healthy','sick','recovering','quarantined','deceased'] as $s): ?>
            <option value="<?= $s ?>" <?= $animal['health_status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="mt-16">
        <label class="ff-form-label">Notes</label>
        <textarea name="notes" class="ff-form-control"><?= e($animal['notes'] ?? '') ?></textarea>
      </div>
      <div class="d-flex gap-12 mt-16">
        <button type="submit" class="ff-btn ff-btn-primary"><i class="bi bi-check-circle"></i> Update Animal</button>
        <a href="index.php" class="ff-btn ff-btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
