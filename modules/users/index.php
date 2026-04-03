<?php
// modules/users/index.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';

Auth::start();
Auth::require();
RBAC::require('users', 'view');

$users = DB::rows("
    SELECT u.*, f.name AS farm_name
    FROM users u
    LEFT JOIN farms f ON f.id = u.farm_id
    ORDER BY u.created_at DESC
");

$pageTitle = 'Users';
$activeNav = 'users';
include __DIR__ . '/../../templates/header.php';
?>

<div class="ff-page-header">
  <div>
    <h2 class="ff-page-title">👥 Users</h2>
    <p class="ff-page-subtitle">Manage system users and permissions</p>
  </div>
  <?php if (RBAC::can('users', 'create')): ?>
  <a href="create.php" class="ff-btn ff-btn-primary"><i class="bi bi-person-plus"></i> Add User</a>
  <?php endif; ?>
</div>

<div class="ff-card">
  <div class="table-responsive">
    <table class="ff-table" id="usersTable">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th data-sortable>Role</th>
          <th>Assigned Farm</th>
          <th>Notifications</th>
          <th>Last Login</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u):
          $roleColors = ['owner'=>'danger','manager'=>'warning','viewer'=>'secondary'];
          $rc = $roleColors[$u['role']] ?? 'secondary';
          $initials = strtoupper(substr($u['name'], 0, 2));
        ?>
        <tr>
          <td>
            <div class="d-flex align-center gap-12">
              <div style="width:36px;height:36px;border-radius:50%;background:var(--color-brand-light);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px;flex-shrink:0;">
                <?= e($initials) ?>
              </div>
              <div>
                <div style="font-weight:600;"><?= e($u['name']) ?></div>
                <?php if ($u['id'] === Auth::id()): ?>
                <div style="font-size:11px;color:var(--color-brand-light);">You</div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td style="font-size:13px;"><?= e($u['email']) ?></td>
          <td><span class="ff-badge ff-badge-<?= $rc ?>"><?= e(ucfirst($u['role'])) ?></span></td>
          <td><?= $u['farm_name'] ? e($u['farm_name']) : '<span style="color:var(--text-muted)">All Farms</span>' ?></td>
          <td>
            <?php if ($u['email_notifications']): ?>
            <span class="ff-badge ff-badge-success"><i class="bi bi-bell-fill"></i> On</span>
            <?php else: ?>
            <span class="ff-badge ff-badge-secondary"><i class="bi bi-bell-slash"></i> Off</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--text-muted);">
            <?= $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : 'Never' ?>
          </td>
          <td>
            <div class="d-flex gap-8">
              <?php if (RBAC::can('users', 'edit')): ?>
              <a href="edit.php?id=<?= $u['id'] ?>" class="ff-btn ff-btn-outline ff-btn-sm"><i class="bi bi-pencil"></i></a>
              <?php endif; ?>
              <?php if (RBAC::can('users', 'delete') && $u['id'] !== Auth::id()): ?>
              <form method="POST" action="delete.php" id="del-u-<?= $u['id'] ?>" style="display:inline;">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button type="button" class="ff-btn ff-btn-danger ff-btn-sm" onclick="confirmDelete('del-u-<?= $u['id'] ?>','Delete user <?= e($u['name']) ?>?')">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>document.addEventListener('DOMContentLoaded', () => TableSorter.init('usersTable'));</script>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
