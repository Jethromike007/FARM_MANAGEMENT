<?php
// modules/users/create.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';
require_once __DIR__ . '/../../config/mailer.php';

Auth::start();
Auth::require();
RBAC::require('users', 'create');

$errors = [];
$farms  = DB::rows("SELECT id, name FROM farms ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf()) { $errors[] = 'Invalid CSRF token.'; }

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $role     = $_POST['role'] ?? 'viewer';
    $farm_id  = (int)($_POST['farm_id'] ?? 0) ?: null;
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;

    if (!$name)             $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (DB::row("SELECT id FROM users WHERE email = ?", [$email])) $errors[] = 'Email already in use.';

    // Only owner can create other owners
    if ($role === 'owner' && Auth::role() !== 'owner') {
        $errors[] = 'Only owners can create owner accounts.';
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $id = DB::insert(
            "INSERT INTO users (name, email, password, role, farm_id, email_notifications) VALUES (?, ?, ?, ?, ?, ?)",
            [$name, $email, $hashed, $role, $farm_id, $email_notifications]
        );
        Logger::log(Auth::id(), 'create', 'users', $id, "Created user: {$name} ({$role})");

        // Welcome email
        Mailer::send($email, $name, 'Welcome to ' . APP_NAME,
            "<p>Hi {$name},</p><p>Your account has been created with role: <strong>" . ucfirst($role) . "</strong>.</p>
             <p>Login at: <a href='" . APP_URL . "/auth/login.php'>" . APP_URL . "/auth/login.php</a></p>
             <p>Your temporary password: <strong>{$password}</strong><br>Please change it after first login.</p>");

        flash('success', "User '{$name}' created and welcome email sent.");
        redirect(APP_URL . '/modules/users/index.php');
    }
}

$pageTitle = 'Add User';
$activeNav = 'users';
include __DIR__ . '/../../templates/header.php';
?>

<div class="ff-page-header">
  <div><h2 class="ff-page-title">Add User</h2></div>
  <a href="index.php" class="ff-btn ff-btn-outline"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php foreach ($errors as $err): ?>
<div class="ff-alert ff-alert-error"><i class="bi bi-x-circle-fill"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<div class="ff-card" style="max-width:600px;">
  <div class="ff-card-header"><span class="ff-card-title">New User Details</span></div>
  <div class="ff-card-body">
    <form method="POST">
      <?= Auth::csrfField() ?>
      <div class="grid-2" style="gap:18px;">
        <div>
          <label class="ff-form-label">Full Name *</label>
          <input type="text" name="name" class="ff-form-control" required placeholder="e.g. Amaka Obi" value="<?= e($_POST['name'] ?? '') ?>">
        </div>
        <div>
          <label class="ff-form-label">Email Address *</label>
          <input type="email" name="email" class="ff-form-control" required placeholder="user@example.com" value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <div>
          <label class="ff-form-label">Password * <small style="color:var(--text-muted);">(min 8 chars)</small></label>
          <input type="password" name="password" class="ff-form-control" required minlength="8">
        </div>
        <div>
          <label class="ff-form-label">Confirm Password *</label>
          <input type="password" name="confirm_password" class="ff-form-control" required minlength="8">
        </div>
        <div>
          <label class="ff-form-label">Role *</label>
          <select name="role" class="ff-form-control">
            <?php $roles = Auth::role() === 'owner' ? ['owner','manager','viewer'] : ['manager','viewer']; ?>
            <?php foreach ($roles as $r): ?>
            <option value="<?= $r ?>" <?= (($_POST['role'] ?? 'viewer') === $r) ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="ff-form-label">Assigned Farm <small style="color:var(--text-muted);">(leave blank for all)</small></label>
          <select name="farm_id" class="ff-form-control">
            <option value="">All Farms (Owner only)</option>
            <?php foreach ($farms as $f): ?>
            <option value="<?= $f['id'] ?>" <?= (($_POST['farm_id'] ?? '') == $f['id']) ? 'selected' : '' ?>><?= e($f['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="grid-column:1/-1;">
          <label class="ff-form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="email_notifications" <?= isset($_POST['email_notifications']) ? 'checked' : 'checked' ?> style="width:16px;height:16px;">
            Enable email notifications for this user
          </label>
        </div>
      </div>
      <div class="d-flex gap-12 mt-16">
        <button type="submit" class="ff-btn ff-btn-primary"><i class="bi bi-person-plus"></i> Create User</button>
        <a href="index.php" class="ff-btn ff-btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
