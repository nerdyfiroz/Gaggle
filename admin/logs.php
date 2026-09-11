<?php
/**
 * Gaggle NFT — Admin Audit Logs
 */
require_once dirname(__DIR__) . '/app/middleware/AuthMiddleware.php';
require_admin_auth();

$logModel = new AdminLog();

$filters = [];
if (!empty($_GET['action'])) $filters['action'] = sanitize($_GET['action']);
if (!empty($_GET['date_from'])) $filters['date_from'] = sanitize($_GET['date_from']);
if (!empty($_GET['date_to'])) $filters['date_to'] = sanitize($_GET['date_to']);
$page = max(1, (int) ($_GET['page'] ?? 1));

$result = $logModel->getAll($filters, $page, 50);
$actionTypes = $logModel->getActionTypes();

$pageTitle = 'Audit Logs';
ob_start();
?>

<form method="GET" class="filter-bar">
    <select name="action">
        <option value="">All Actions</option>
        <?php foreach ($actionTypes as $type): ?>
            <option value="<?= e($type) ?>" <?= ($filters['action'] ?? '') === $type ? 'selected' : '' ?>><?= e($type) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="date" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>">
    <input type="date" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>">
    <button type="submit" class="btn-admin btn-admin-primary btn-admin-sm"><i class="bi bi-search"></i> Filter</button>
    <a href="/admin/logs.php" class="btn-admin btn-admin-secondary btn-admin-sm">Clear</a>
</form>

<div class="admin-card">
    <div class="admin-card-header">
        <h3>Audit Logs (<?= number_format($result['total']) ?>)</h3>
    </div>
    <div class="admin-card-body" style="padding: 0; overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr><th>Date</th><th>Admin</th><th>Action</th><th>Target</th><th>Details</th><th>IP</th></tr>
            </thead>
            <tbody>
                <?php if (empty($result['data'])): ?>
                    <tr><td colspan="6" style="text-align: center; color: var(--admin-text-muted); padding: 30px;">No logs found.</td></tr>
                <?php else: foreach ($result['data'] as $entry): ?>
                <tr>
                    <td style="font-size: 0.8rem; white-space: nowrap;"><?= e(format_date($entry['created_at'], 'M j H:i')) ?></td>
                    <td><?= e($entry['admin_username'] ?? '—') ?></td>
                    <td><span class="badge badge-info"><?= e($entry['action']) ?></span></td>
                    <td style="font-size: 0.85rem;"><?= $entry['target_type'] ? e($entry['target_type']) . ': ' . e($entry['target_id'] ?? '') : '—' ?></td>
                    <td style="font-size: 0.85rem; color: var(--admin-text-muted); max-width: 200px; overflow: hidden; text-overflow: ellipsis;"><?= e($entry['details'] ?? '') ?></td>
                    <td style="font-size: 0.8rem;"><code><?= e($entry['ip_address'] ?? '') ?></code></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($result['total_pages'] > 1): ?>
<div class="pagination">
    <?php
    $qs = http_build_query(array_filter($filters));
    $base = '/admin/logs.php?' . ($qs ? $qs . '&' : '');
    for ($i = max(1, $result['page'] - 2); $i <= min($result['total_pages'], $result['page'] + 2); $i++):
    ?>
        <a href="<?= $base ?>page=<?= $i ?>" class="<?= $i === $result['page'] ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include VIEWS_PATH . '/layouts/admin.php';
?>
