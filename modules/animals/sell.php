<?php
// modules/animals/sell.php — Record a sale
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';
require_once __DIR__ . '/../../config/mailer.php';

Auth::start();
Auth::require();
RBAC::require('animals', 'sell');

$id     = (int)($_GET['id'] ?? 0);
$animal = DB::row("SELECT a.*, f.name AS farm_name FROM animals a JOIN farms f ON f.id = a.farm_id WHERE a.id = ?", [$id]);
if (!$animal) { flash('error', 'Animal not found.'); redirect(APP_URL . '/modules/animals/index.php'); }
if ($animal['sold']) { flash('warning', 'This animal has already been sold.'); redirect(APP_URL . '/modules/animals/index.php'); }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf()) { $errors[] = 'Invalid CSRF token.'; }

    $sold_date  = $_POST['sold_date'] ?? date('Y-m-d');
    $price_sold = (float)($_POST['price_sold'] ?? 0);
    $qty_sold   = (int)($_POST['qty_sold'] ?? $animal['quantity']);
    $unit_price = $qty_sold > 0 ? round($price_sold / $qty_sold, 2) : 0;
    $buyer_name = trim($_POST['buyer_name'] ?? '');

    if ($price_sold <= 0) $errors[] = 'Sale price must be greater than 0.';
    if (!$sold_date) $errors[] = 'Sale date is required.';

    if (empty($errors)) {
        // Mark animal as sold
        DB::execute(
            "UPDATE animals SET sold = 1, sold_date = ?, price_sold = ? WHERE id = ?",
            [$sold_date, $price_sold, $id]
        );

        // Insert into sales ledger
        $saleId = DB::insert(
            "INSERT INTO sales (farm_id, entity_type, entity_id, sale_date, quantity, unit_price, buyer_name, recorded_by)
             VALUES (?, 'animal', ?, ?, ?, ?, ?, ?)",
            [$animal['farm_id'], $id, $sold_date, $qty_sold, $unit_price, $buyer_name, Auth::id()]
        );

        Logger::log(Auth::id(), 'sell', 'animals', $id, "Sold {$animal['type']} (#{$id}) for " . money($price_sold));

        // Email notification
        $user = Auth::user();
        if ($_SESSION['email_notifications']) {
            Mailer::send($user['email'], $user['name'],
                'Animal Sold — ' . APP_NAME,
                "<p>{$animal['quantity']} {$animal['type']} sold for " . money($price_sold) . " on {$sold_date}.</p>"
            );
        }

        flash('success', "{$animal['type']} sold for " . money($price_sold) . " recorded successfully.");
        redirect(APP_URL . '/modules/animals/index.php');
    }
}

$pageTitle = 'Record Animal Sale';
$activeNav = 'animals';
include __DIR__ . '/../../templates/header.php';
?>

<div class="ff-page-header">
  <div>
    <h2 class="ff-page-title">💰 Record Sale</h2>
    <p class="ff-page-subtitle"><?= e($animal['type']) ?> — <?= e($animal['farm_name']) ?></p>
  </div>
  <a href="index.php" class="ff-btn ff-btn-outline"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php foreach ($errors as $err): ?>
<div class="ff-alert ff-alert-error"><i class="bi bi-x-circle-fill"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<div class="ff-card" style="max-width:520px;">
  <div class="ff-card-header">
    <span class="ff-card-title">Sale Details</span>
    <span class="ff-badge ff-badge-success">Ready to Sell</span>
  </div>
  <div class="ff-card-body">
    <!-- Animal summary -->
    <div class="ff-alert ff-alert-info mb-16">
      <i class="bi bi-info-circle-fill"></i>
      <span><?= e($animal['quantity']) ?> × <?= e($animal['type']) ?><?= $animal['breed'] ? ' (' . e($animal['breed']) . ')' : '' ?> from <?= e($animal['farm_name']) ?></span>
    </div>

    <form method="POST">
      <?= Auth::csrfField() ?>
      <div style="display:grid;gap:16px;">
        <div>
          <label class="ff-form-label">Sale Date *</label>
          <input type="date" name="sold_date" class="ff-form-control" required value="<?= date('Y-m-d') ?>">
        </div>
        <div>
          <label class="ff-form-label">Quantity Sold</label>
          <input type="number" name="qty_sold" class="ff-form-control" min="1" max="<?= $animal['quantity'] ?>" value="<?= $animal['quantity'] ?>">
        </div>
        <div>
          <label class="ff-form-label">Total Sale Price (₦) *</label>
          <input type="number" name="price_sold" class="ff-form-control" min="1" step="0.01" required placeholder="e.g. 850000">
        </div>
        <div>
          <label class="ff-form-label">Buyer Name</label>
          <input type="text" name="buyer_name" class="ff-form-control" placeholder="Optional">
        </div>
      </div>
      <div class="d-flex gap-12 mt-16">
        <button type="submit" class="ff-btn ff-btn-success"><i class="bi bi-currency-dollar"></i> Record Sale</button>
        <a href="index.php" class="ff-btn ff-btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
