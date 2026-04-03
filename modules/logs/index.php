<?php
// modules/logs/index.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/rbac_logger_helpers.php';

Auth::start();
Auth::require();
RBAC::require('logs', 'view');

$search     = $_GET['q'] ?? '';
$action     = $_GET['action'] ?? '';
$entity     = $_GET['entity'] ?? '';
$userId     = $_GET['user_id'] ?? '';
$dateFrom   = $_GET['date_from'] ?? date('Y-m-01');
$dateTo     = $_GET['date_to']   ?? date('Y-m-d');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = ITEMS_PER_PAGE;

$conditions = ["l.timestamp BETWEEN ? AND ?"];
$params     = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

if ($search) { $conditions[] = "(l.description LIKE ? OR u.name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($action)  { $conditions[] = "l.action_type = ?"; $params[] = $action; }
if ($entity)  { $conditions[] = "l.entity_type = ?"; $params[] = $entity; }
if ($userId)  { $conditions[] = "l.user_id = ?"; $params[] = (int)$userId; }

$sqlWhere = 'WHERE ' . implode(' AND ', $conditions);

// Count for pagination
$total = DB::row("SELECT COUNT(*) AS cnt FROM logs l LEFT JOIN users u ON u.id = l.user_id $sqlWhere", $params)['cnt'];
$totalPages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$logs = DB::rows("
    SELECT l.*, u.name AS user_name
    FROM logs l
    LEFT JOIN users u ON u.id = l.user_id
    $sqlWhere
    ORDER BY l.timestamp DESC
    LIMIT $perPage OFFSET $offset
", $params);

$users       = DB::rows("SELECT id, name FROM users ORDER BY name");
$actionTypes = ['create','update','delete','login','logout','sell','harvest','record_eggs','email_sent'];
$entityTypes = ['farms','animals','crops','egg_production','sales','users'];

$pageTitle = 'Audit Logs';
$activeNav = 'logs';
include __DIR__ . '/../../templates/header.php';
?>

<div class="ff-page-header">
  <div>
    <h2 class="ff-page-title">📋 Audit Logs</h2>
    <p class="ff-page-subtitle">Complete system activity trail — <?= number_format($total) ?> entries</p>
  </div>
</div>

<!-- Filters -->
<div class="ff-card mb-24">
  <form method="GET" style="padding:16px 22px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
    <div class="ff-search-wrap" style="min-width:200px;flex:1;">
      <i class="bi bi-search search-icon"></i>
      <input class="ff-search-input w-100" type="text" name="q" placeholder="Search description or user…" value="<?= e($search) ?>">
    </div>
    <select name="action" class="ff-form-control" style="width:auto;padding:8px 12px;">
      <option value="">All Actions</option>
      <?php foreach ($actionTypes as $at): ?>
      <option value="<?= $at ?>" <?= $action === $at ? 'selected' : '' ?>><?= ucfirst($at) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="entity" class="ff-form-control" style="width:auto;padding:8px 12px;">
      <option value="">All Entities</option>
      <?php foreach ($entityTypes as $et): ?>
      <option value="<?= $et ?>" <?= $entity === $et ? 'selected' : '' ?>><?= ucfirst($et) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="user_id" class="ff-form-control" style="width:auto;padding:8px 12px;">
      <option value="">All Users</option>
      <?php foreach ($users as $u): ?>
      <option value="<?= $u['id'] ?>" <?= $userId == $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <div style="display:flex;align-items:center;gap:6px;">
      <input type="date" name="date_from" class="ff-form-control" value="<?= e($dateFrom) ?>" style="width:auto;">
      <span style="color:var(--text-muted);">to</span>
      <input type="date" name="date_to" class="ff-form-control" value="<?= e($dateTo) ?>" style="width:auto;">
    </div>
    <button type="submit" class="ff-btn ff-btn-primary ff-btn-sm"><i class="bi bi-funnel"></i> Filter</button>
    <a href="index.php" class="ff-btn ff-btn-outline ff-btn-sm">Clear</a>
  </form>
</div>

<div class="ff-card">
  <div class="table-responsive">
    <table class="ff-table" id="logsTable">
      <thead>
        <tr>
          <th data-sortable>Timestamp</th>
          <th>User</th>
          <th data-sortable>Action</th>
          <th>Entity</th>
          <th>Description</th>
          <th>IP Address</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $log):
          $actionColors = [
            'create' => 'success', 'update' => 'info', 'delete' => 'danger',
            'login' => 'secondary', 'logout' => 'secondary',
            'sell' => 'warning', 'harvest' => 'success',
            'record_eggs' => 'info', 'email_sent' => 'secondary',
          ];
          $ac = $actionColors[$log['action_type']] ?? 'secondary';
        ?>
        <tr>
          <td class="text-mono" style="font-size:12px;white-space:nowrap;">
            <?= date('d M Y H:i:s', strtotime($log['timestamp'])) ?>
          </td>
          <td style="font-weight:500;"><?= e($log['user_name'] ?? 'System') ?></td>
          <td><span class="ff-badge ff-badge-<?= $ac ?>"><?= e($log['action_type']) ?></span></td>
          <td style="font-size:12px;color:var(--text-muted);">
            <?php if ($log['entity_type']): ?>
              <?= e($log['entity_type']) ?> <?= $log['entity_id'] ? '#' . $log['entity_id'] : '' ?>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td style="max-width:340px;"><?= e($log['description']) ?></td>
          <td class="text-mono" style="font-size:11px;color:var(--text-muted);"><?= e($log['ip_address'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
        <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted);">No log entries found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <div class="ff-pagination">
    <span>Showing <?= number_format(($page-1)*$perPage+1) ?>–<?= number_format(min($page*$perPage, $total)) ?> of <?= number_format($total) ?></span>
    <div class="ff-pagination-btns">
      <?php
      $qBase = http_build_query(array_filter(['q'=>$search,'action'=>$action,'entity'=>$entity,'user_id'=>$userId,'date_from'=>$dateFrom,'date_to'=>$dateTo]));
      for ($p = 1; $p <= $totalPages; $p++):
        if ($totalPages > 10 && abs($p - $page) > 2 && $p !== 1 && $p !== $totalPages) continue;
      ?>
      <a href="?<?= $qBase ?>&page=<?= $p ?>" class="ff-pagination-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
  </div>
</div>

<script>document.addEventListener('DOMContentLoaded', () => TableSorter.init('logsTable'));</script>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
