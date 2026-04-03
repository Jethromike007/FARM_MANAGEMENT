<?php
// modules/animals/create.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';
require_once __DIR__ . '/../../config/mailer.php';

Auth::start();
Auth::require();
RBAC::require('animals', 'create');

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

    if (!$farm_id)        $errors[] = 'Please select a farm.';
    if (!$type)           $errors[] = 'Animal type is required.';
    if ($quantity < 1)    $errors[] = 'Quantity must be at least 1.';
    if (!$birth_date)     $errors[] = 'Birth date is required.';
    if ($maturity_days < 1) $errors[] = 'Maturity days must be at least 1.';

    // RBAC farm scope check
    if (RBAC::farmScope() && $farm_id !== RBAC::farmScope()) {
        $errors[] = 'You are not authorized to add animals to that farm.';
    }

    if (empty($errors)) {
        $id = DB::insert(
            "INSERT INTO animals (farm_id, type, breed, quantity, birth_date, maturity_days, health_status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$farm_id, $type, $breed, $quantity, $birth_date, $maturity_days, $health_status, $notes]
        );

        Logger::log(Auth::id(), 'create', 'animals', $id, "Added {$quantity} {$type} to farm #{$farm_id}");

        // Event email — fetch current user from DB to avoid undefined $user
        $user = DB::row("SELECT name, email, email_notifications FROM users WHERE id = ?", [Auth::id()]);
        if ($user && $user['email_notifications']) {
            Mailer::send(
                $user['email'], $user['name'],
                'New Animal Added — ' . APP_NAME,
                "<p>Hi {$user['name']},</p><p>{$quantity} {$type} have been added to farm #{$farm_id}.</p>"
            );
        }

        flash('success', "{$quantity} {$type} added successfully.");
        redirect(APP_URL . '/modules/animals/index.php');
    }
}

$pageTitle = 'Add Animal';
$activeNav = 'animals';
include __DIR__ . '/../../templates/header.php';
?>

<div class="ff-page-header">
  <div>
    <h2 class="ff-page-title">Add Animal</h2>
    <p class="ff-page-subtitle">Register a new animal batch to your farm</p>
  </div>
  <a href="index.php" class="ff-btn ff-btn-outline"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php foreach ($errors as $err): ?>
<div class="ff-alert ff-alert-error"><i class="bi bi-x-circle-fill"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<div class="ff-card" style="max-width:680px;">
  <div class="ff-card-header"><span class="ff-card-title">Animal Details</span></div>
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
            <option value="<?= $f['id'] ?>" <?= (isset($_POST['farm_id']) && $_POST['farm_id'] == $f['id']) ? 'selected' : '' ?>>
              <?= e($f['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="ff-form-label">Animal Type *</label>
          <input type="text" name="type" class="ff-form-control" placeholder="e.g. Cattle, Goat, Chicken" required value="<?= e($_POST['type'] ?? '') ?>">
        </div>

        <div>
          <label class="ff-form-label">Breed</label>
          <input type="text" name="breed" class="ff-form-control" placeholder="e.g. Bunaji, Broiler" value="<?= e($_POST['breed'] ?? '') ?>">
        </div>

        <div>
          <label class="ff-form-label">Quantity *</label>
          <input type="number" name="quantity" class="ff-form-control" min="1" required value="<?= e($_POST['quantity'] ?? 1) ?>">
        </div>

        <div>
          <label class="ff-form-label">Birth / Arrival Date *</label>
          <input type="date" name="birth_date" class="ff-form-control" required value="<?= e($_POST['birth_date'] ?? '') ?>">
        </div>

        <div>
          <label class="ff-form-label">Days to Maturity *</label>
          <input type="number" name="maturity_days" class="ff-form-control" min="1" placeholder="e.g. 180" required value="<?= e($_POST['maturity_days'] ?? '') ?>">
          <small style="color:var(--text-muted);font-size:11px;">Days from birth date until ready to sell</small>
        </div>

        <div>
          <label class="ff-form-label">Health Status</label>
          <select name="health_status" class="ff-form-control">
            <?php foreach (['healthy','sick','recovering','quarantined'] as $s): ?>
            <option value="<?= $s ?>" <?= (($_POST['health_status'] ?? 'healthy') === $s) ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

      </div>

      <div class="mt-16">
        <label class="ff-form-label">Notes</label>
        <textarea name="notes" class="ff-form-control" placeholder="Optional notes…"><?= e($_POST['notes'] ?? '') ?></textarea>
      </div>

      <div class="d-flex gap-12 mt-16">
        <button type="submit" class="ff-btn ff-btn-primary"><i class="bi bi-check-circle"></i> Save Animal</button>
        <a href="index.php" class="ff-btn ff-btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>