<?php
// modules/crops/harvest.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';
require_once __DIR__ . '/../../config/mailer.php';

Auth::start();
Auth::require();
RBAC::require('crops', 'harvest');

$id   = (int)($_GET['id'] ?? 0);
$crop = DB::row("SELECT c.*, f.name AS farm_name FROM crops c JOIN farms f ON f.id=c.farm_id WHERE c.id = ?", [$id]);
if (!$crop) { flash('error', 'Crop not found.'); redirect(APP_URL . '/modules/crops/index.php'); }
if ($crop['harvested']) { flash('warning', 'Already harvested.'); redirect(APP_URL . '/modules/crops/index.php'); }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf()) { $errors[] = 'Invalid CSRF token.'; }

    $harvested_date = $_POST['harvested_date'] ?? date('Y-m-d');
    $price_sold     = (float)($_POST['price_sold'] ?? 0);
    $qty_sold       = (float)($_POST['qty_sold'] ?? $crop['quantity']);
    $unit_price     = $qty_sold > 0 ? round($price_sold / $qty_sold, 2) : 0;
    $buyer_name     = trim($_POST['buyer_name'] ?? '');

    if (!$harvested_date) $errors[] = 'Harvest date is required.';

    if (empty($errors)) {
        DB::execute(
            "UPDATE crops SET harvested = 1, harvested_date = ?, price_sold = ? WHERE id = ?",
            [$harvested_date, $price_sold ?: null, $id]
        );

        if ($price_sold > 0) {
            DB::insert(
                "INSERT INTO sales (farm_id, entity_type, entity_id, sale_date, quantity, unit_price, buyer_name, recorded_by)
                 VALUES (?, 'crop', ?, ?, ?, ?, ?, ?)",
                [$crop['farm_id'], $id, $harvested_date, $qty_sold, $unit_price, $buyer_name, Auth::id()]
            );
        }

        Logger::log(Auth::id(), 'harvest', 'crops', $id,
            "Harvested {$crop['type']} (#{$id})" . ($price_sold > 0 ? " — sold for " . money($price_sold) : ""));

        $user = Auth::user();
        if ($_SESSION['email_notifications']) {
            Mailer::send($user['email'], $user['name'], 'Crop Harvested — ' . APP_NAME,
                "<p>{$crop['type']} crop harvested on {$harvested_date}" . ($price_sold > 0 ? " for " . money($price_sold) : "") . ".</p>");
        }

        flash('success', "{$crop['type']} harvest recorded successfully.");
        redirect(APP_URL . '/modules/crops/index.php');
    }
}

$pageTitle = 'Record Harvest';
$activeNav = 'crops';
include __DIR__ . '/../../templates/header.php';
?>

<div class="ff-page-header">
  <div>
    <h2 class="ff-page-title">✂️ Record Harvest</h2>
    <p class="ff-page-subtitle"><?= e($crop['type']) ?> — <?= e($crop['farm_name']) ?></p>
  </div>
  <a href="index.php" class="ff-btn ff-btn-outline"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php foreach ($errors as $err): ?>
<div class="ff-alert ff-alert-error"><i class="bi bi-x-circle-fill"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<div class="ff-card" style="max-width:520px;">
  <div class="ff-card-header">
    <span class="ff-card-title">Harvest Details</span>
    <span class="ff-badge ff-badge-success">Ready to Harvest</span>
  </div>
  <div class="ff-card-body">
    <div class="ff-alert ff-alert-info mb-16">
      <i class="bi bi-info-circle-fill"></i>
      <span><?= e($crop['quantity']) ?> <?= e($crop['quantity_unit']) ?> of <?= e($crop['type']) ?><?= $crop['variety'] ? ' (' . e($crop['variety']) . ')' : '' ?> from <?= e($crop['farm_name']) ?></span>
    </div>

    <form method="POST">
      <?= Auth::csrfField() ?>
      <div style="display:grid;gap:16px;">
        <div>
          <label class="ff-form-label">Harvest Date *</label>
          <input type="date" name="harvested_date" class="ff-form-control" required value="<?= date('Y-m-d') ?>">
        </div>
        <div>
          <label class="ff-form-label">Quantity Harvested (<?= e($crop['quantity_unit']) ?>)</label>
          <input type="number" name="qty_sold" class="ff-form-control" step="0.01" value="<?= e($crop['quantity']) ?>">
        </div>
        <div>
          <label class="ff-form-label">Total Sale Price (₦) <small style="color:var(--text-muted);">Leave 0 if not sold yet</small></label>
          <input type="number" name="price_sold" class="ff-form-control" step="0.01" min="0" placeholder="0.00">
        </div>
        <div>
          <label class="ff-form-label">Buyer Name</label>
          <input type="text" name="buyer_name" class="ff-form-control" placeholder="Optional">
        </div>
      </div>
      <div class="d-flex gap-12 mt-16">
        <button type="submit" class="ff-btn ff-btn-success"><i class="bi bi-scissors"></i> Record Harvest</button>
        <a href="index.php" class="ff-btn ff-btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
