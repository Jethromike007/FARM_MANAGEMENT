<?php
// modules/farms/create.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';

Auth::start();
Auth::require();
RBAC::require('farms', 'create');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf()) { $errors[] = 'Invalid CSRF token.'; }

    $name  = trim($_POST['name'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $city  = trim($_POST['city'] ?? '');
    $type  = $_POST['type'] ?? 'mixed';
    $size  = (float)($_POST['size'] ?? 0);

    if (!$name)   $errors[] = 'Farm name is required.';
    if (!$state)  $errors[] = 'State is required.';
    if (!$city)   $errors[] = 'City is required.';
    if ($size <= 0) $errors[] = 'Size must be greater than 0.';

    if (empty($errors)) {
        $id = DB::insert(
            "INSERT INTO farms (name, state, city, type, size) VALUES (?, ?, ?, ?, ?)",
            [$name, $state, $city, $type, $size]
        );
        Logger::log(Auth::id(), 'create', 'farms', $id, "Created farm: {$name} in {$city}, {$state}");
        flash('success', "Farm '{$name}' created successfully.");
        redirect(APP_URL . '/modules/farms/index.php');
    }
}

$nigerianStates = ['Abia','Adamawa','Akwa Ibom','Anambra','Bauchi','Bayelsa','Benue','Borno','Cross River',
    'Delta','Ebonyi','Edo','Ekiti','Enugu','FCT','Gombe','Imo','Jigawa','Kaduna','Kano','Katsina',
    'Kebbi','Kogi','Kwara','Lagos','Nasarawa','Niger','Ogun','Ondo','Osun','Oyo','Plateau','Rivers',
    'Sokoto','Taraba','Yobe','Zamfara'];

$pageTitle = 'Add Farm';
$activeNav = 'farms';
include __DIR__ . '/../../templates/header.php';
?>

<div class="ff-page-header">
  <div><h2 class="ff-page-title">Add Farm</h2></div>
  <a href="index.php" class="ff-btn ff-btn-outline"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php foreach ($errors as $err): ?>
<div class="ff-alert ff-alert-error"><i class="bi bi-x-circle-fill"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<div class="ff-card" style="max-width:580px;">
  <div class="ff-card-header"><span class="ff-card-title">Farm Details</span></div>
  <div class="ff-card-body">
    <form method="POST">
      <?= Auth::csrfField() ?>
      <div class="grid-2" style="gap:18px;">
        <div style="grid-column:1/-1;">
          <label class="ff-form-label">Farm Name *</label>
          <input type="text" name="name" class="ff-form-control" required placeholder="e.g. Green Valley Farm" value="<?= e($_POST['name'] ?? '') ?>">
        </div>
        <div>
          <label class="ff-form-label">State *</label>
          <select name="state" class="ff-form-control" required>
            <option value="">Select state…</option>
            <?php foreach ($nigerianStates as $s): ?>
            <option value="<?= $s ?>" <?= (($_POST['state'] ?? '') === $s) ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="ff-form-label">City *</label>
          <input type="text" name="city" class="ff-form-control" required placeholder="e.g. Ikorodu" value="<?= e($_POST['city'] ?? '') ?>">
        </div>
        <div>
          <label class="ff-form-label">Farm Type</label>
          <select name="type" class="ff-form-control">
            <?php foreach (['mixed'=>'Mixed','crop'=>'Crop','livestock'=>'Livestock','poultry'=>'Poultry','aquaculture'=>'Aquaculture','orchard'=>'Orchard'] as $val => $label): ?>
            <option value="<?= $val ?>" <?= (($_POST['type'] ?? 'mixed') === $val) ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="ff-form-label">Size (Hectares) *</label>
          <input type="number" name="size" class="ff-form-control" step="0.01" min="0.01" required placeholder="e.g. 45.5" value="<?= e($_POST['size'] ?? '') ?>">
        </div>
      </div>
      <div class="d-flex gap-12 mt-16">
        <button type="submit" class="ff-btn ff-btn-primary"><i class="bi bi-check-circle"></i> Save Farm</button>
        <a href="index.php" class="ff-btn ff-btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
