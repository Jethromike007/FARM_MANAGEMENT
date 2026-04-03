<?php
// modules/users/edit.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';

Auth::start();
Auth::require();
RBAC::require('users', 'edit');

$id       = (int)($_GET['id'] ?? 0);
$editUser = DB::row("SELECT * FROM users WHERE id = ?", [$id]);
if (!$editUser) { flash('error', 'User not found.'); redirect(APP_URL . '/modules/users/index.php'); }

$errors = [];
$farms  = DB::rows("SELECT id, name FROM farms ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf()) { $errors[] = 'Invalid CSRF token.'; }

    $name                = trim($_POST['name'] ?? '');
    $email               = trim($_POST['email'] ?? '');
    $role                = $_POST['role'] ?? $editUser['role'];
    $farm_id             = (int)($_POST['farm_id'] ?? 0) ?: null;
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $newPass             = $_POST['new_password'] ?? '';

    if (!$name) $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';

    $existing = DB::row("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $id]);
    if ($existing) $errors[] = 'Email already in use by another user.';

    if ($newPass && strlen($newPass) < 8) $errors[] = 'New password must be at least 8 characters.';

    if ($role === 'owner' && Auth::role() !== 'owner') $errors[] = 'Only owners can assign owner role.';

    // Re-populate $editUser with submitted values so form re-renders correctly on error
    $editUser['name']                = $name;
    $editUser['email']               = $email;
    $editUser['role']                = $role;
    $editUser['farm_id']             = $farm_id;
    $editUser['email_notifications'] = $email_notifications;

    if (empty($errors)) {
        if ($newPass) {
            $hashed = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            DB::execute(
                "UPDATE users SET name=?, email=?, role=?, farm_id=?, email_notifications=?, password=? WHERE id=?",
                [$name, $email, $role, $farm_id, $email_notifications, $hashed, $id]
            );
        } else {
            DB::execute(
                "UPDATE users SET name=?, email=?, role=?, farm_id=?, email_notifications=? WHERE id=?",
                [$name, $email, $role, $farm_id, $email_notifications, $id]
            );
        }
        Logger::log(Auth::id(), 'update', 'users', $id, "Updated user #{$id}: {$name}");
        // If editing self, refresh session
        if ($id === Auth::id()) {
            $_SESSION['user_name']           = $name;
            $_SESSION['user_email']          = $email;
            $_SESSION['user_role']           = $role;
            $_SESSION['email_notifications'] = (bool)$email_notifications;
        }
        flash('success', 'User updated successfully.');
        redirect(APP_URL . '/modules/users/index.php');
    }
}

$pageTitle = 'Edit User';
$activeNav = 'users';
include __DIR__ . '/../../templates/header.php';
?>

<div class="ff-page-header">
  <div><h2 class="ff-page-title">Edit User</h2></div>
  <a href="index.php" class="ff-btn ff-btn-outline"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php foreach ($errors as $err): ?>
<div class="ff-alert ff-alert-error"><i class="bi bi-x-circle-fill"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<div class="ff-card" style="max-width:600px;">
  <div class="ff-card-header"><span class="ff-card-title">Edit: <?= e($editUser['name']) ?></span></div>
  <div class="ff-card-body">
    <form method="POST">
      <?= Auth::csrfField() ?>
      <div class="grid-2" style="gap:18px;">
        <div>
          <label class="ff-form-label">Full Name *</label>
          <input type="text" name="name" class="ff-form-control" required value="<?= e($editUser['name']) ?>">
        </div>
        <div>
          <label class="ff-form-label">Email *</label>
          <input type="email" name="email" class="ff-form-control" required value="<?= e($editUser['email']) ?>">
        </div>
        <div>
          <label class="ff-form-label">Role</label>
          <select name="role" class="ff-form-control" <?= Auth::role() !== 'owner' ? 'disabled' : '' ?>>
            <?php foreach (['owner','manager','viewer'] as $r): ?>
            <option value="<?= $r ?>" <?= $editUser['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="ff-form-label">Assigned Farm</label>
          <select name="farm_id" class="ff-form-control">
            <option value="">All Farms</option>
            <?php foreach ($farms as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $editUser['farm_id'] == $f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:flex;flex-direction:column;justify-content:flex-end;">
          <label class="ff-form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="email_notifications" <?= !empty($editUser['email_notifications']) ? 'checked' : '' ?> style="width:16px;height:16px;">
            Email Notifications
          </label>
        </div>
        <div>
          <label class="ff-form-label">New Password <small style="color:var(--text-muted);">Leave blank to keep current</small></label>
          <input type="password" name="new_password" class="ff-form-control" minlength="8" placeholder="Min 8 characters">
        </div>
      </div>
      <div class="d-flex gap-12 mt-16">
        <button type="submit" class="ff-btn ff-btn-primary"><i class="bi bi-check-circle"></i> Update User</button>
        <a href="index.php" class="ff-btn ff-btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>