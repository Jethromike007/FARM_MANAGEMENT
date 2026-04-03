<?php
// modules/farms/edit.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';

Auth::start();
Auth::require();
RBAC::require('farms', 'edit');

$id   = (int)($_GET['id'] ?? 0);
$farm = DB::row("SELECT * FROM farms WHERE id = ?", [$id]);
if (!$farm) { flash('error', 'Farm not found.'); redirect(APP_URL . '/modules/farms/index.php'); }

$errors = [];
$nigerianStates = ['Abia','Adamawa','Akwa Ibom','Anambra','Bauchi','Bayelsa','Benue','Borno','Cross River',
    'Delta','Ebonyi','Edo','Ekiti','Enugu','FCT','Gombe','Imo','Jigawa','Kaduna','Kano','Katsina',
    'Kebbi','Kogi','Kwara','Lagos','Nasarawa','Niger','Ogun','Ondo','Osun','Oyo','Plateau','Rivers',
    'Sokoto','Taraba','Yobe','Zamfara'];

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
    if ($size <= 0) $errors[] = 'Size must be > 0.';

    if (empty($errors)) {
        DB::execute(
            "UPDATE farms SET name=?, state=?, city=?, type=?, size=? WHERE id=?",
            [$name, $state, $city, $type, $size, $id]
        );
        Logger::log(Auth::id(), 'update', 'farms', $id, "Updated farm #{$id}: {$name}");
        flash('success', 'Farm updated successfully.');
        redirect(APP_URL . '/modules/farms/index.php');
    }
    $farm = array_merge($farm, $_POST);
}

$pageTitle = 'Edit Farm';
$activeNav = 'farms';
include __DIR__ . '/../../templates/header.php';
?>

<div class="ff-page-header">
  <div><h2 class="ff-page-title">Edit Farm</h2></div>
  <a href="index.php" class="ff-btn ff-btn-outline"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php foreach ($errors as $err): ?>
<div class="ff-alert ff-alert-error"><i class="bi bi-x-circle-fill"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<div class="ff-card" style="max-width:580px;">
  <div class="ff-card-header"><span class="ff-card-title">Edit: <?= e($farm['name']) ?></span></div>
  <div class="ff-card-body">
    <form method="POST">
      <?= Auth::csrfField() ?>
      <div class="grid-2" style="gap:18px;">
        <div style="grid-column:1/-1;">
          <label class="ff-form-label">Farm Name *</label>
          <input type="text" name="name" class="ff-form-control" required value="<?= e($farm['name']) ?>">
        </div>
        <div>
          <label class="ff-form-label">State *</label>
          <select name="state" class="ff-form-control" required>
            <?php foreach ($nigerianStates as $s): ?>
            <option value="<?= $s ?>" <?= $farm['state'] === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="ff-form-label">City *</label>
          <input type="text" name="city" class="ff-form-control" required value="<?= e($farm['city']) ?>">
        </div>
        <div>
          <label class="ff-form-label">Farm Type</label>
          <select name="type" class="ff-form-control">
            <?php foreach (['mixed','crop','livestock','poultry','aquaculture','orchard'] as $t): ?>
            <option value="<?= $t ?>" <?= $farm['type'] === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="ff-form-label">Size (Hectares) *</label>
          <input type="number" name="size" class="ff-form-control" step="0.01" required value="<?= e($farm['size']) ?>">
        </div>
      </div>
      <div class="d-flex gap-12 mt-16">
        <button type="submit" class="ff-btn ff-btn-primary"><i class="bi bi-check-circle"></i> Update Farm</button>
        <a href="index.php" class="ff-btn ff-btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
