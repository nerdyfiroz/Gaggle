<?php
/**
 * Gaggle NFT — Admin Blacklist Management
 */
require_once dirname(__DIR__) . '/app/middleware/AuthMiddleware.php';
require_admin_auth();

require_once APP_PATH . '/models/Blacklist.php';

$blacklist = new Blacklist();
$log = new AdminLog();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $type = sanitize($_POST['type'] ?? '');
        $value = sanitize($_POST['value'] ?? '');
        $reason = sanitize($_POST['reason'] ?? '');

        if (empty($type) || empty($value)) {
            flash('error', 'Type and value are required.');
        } elseif ($blacklist->add($type, $value, $reason)) {
            $log->log('blacklist_add', $type, $value, $reason);
            flash('success', 'Entry added to blacklist.');
        } else {
            flash('error', 'Failed to add. Entry may already exist.');
        }
    } elseif ($action === 'remove') {
        $id = (int) ($_POST['blacklist_id'] ?? 0);
        $blacklist->remove($id);
        $log->log('blacklist_remove', 'blacklist', (string) $id);
        flash('success', 'Entry removed from blacklist.');
    }

    redirect('/admin/blacklist.php');
}

$filters = [];
if (!empty($_GET['type'])) $filters['type'] = sanitize($_GET['type']);
if (!empty($_GET['search'])) $filters['search'] = sanitize($_GET['search']);
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = $blacklist->getAll($filters, $page);

$pageTitle = 'Blacklist';
ob_start();
?>

<div style="display: flex; gap: 12px; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap;">
    <!-- Add Form -->
    <form method="POST" class="admin-form" style="display: flex; gap: 8px; flex-wrap: wrap; flex: 1;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <select name="type" required style="padding: 8px; background: var(--admin-bg); border: 1px solid var(--admin-border); border-radius: 8px; color: var(--admin-text);">
            <option value="wallet">Wallet</option>
            <option value="ip">IP</option>
            <option value="twitter">Twitter/X</option>
            <option value="discord">Discord</option>
        </select>
        <input type="text" name="value" placeholder="Value to blacklist..." required style="flex: 1; min-width: 200px; padding: 8px; background: var(--admin-bg); border: 1px solid var(--admin-border); border-radius: 8px; color: var(--admin-text);">
        <input type="text" name="reason" placeholder="Reason (optional)" style="width: 200px; padding: 8px; background: var(--admin-bg); border: 1px solid var(--admin-border); border-radius: 8px; color: var(--admin-text);">
        <button type="submit" class="btn-admin btn-admin-primary btn-admin-sm"><i class="bi bi-plus"></i> Add</button>
    </form>
</div>

<!-- Filter -->
<form method="GET" class="filter-bar" style="margin-bottom: 16px;">
    <select name="type" style="padding: 8px; background: var(--admin-bg); border: 1px solid var(--admin-border); border-radius: 8px; color: var(--admin-text);">
        <option value="">All Types</option>
        <option value="wallet" <?= ($filters['type'] ?? '') === 'wallet' ? 'selected' : '' ?>>Wallet</option>
        <option value="ip" <?= ($filters['type'] ?? '') === 'ip' ? 'selected' : '' ?>>IP</option>
        <option value="twitter" <?= ($filters['type'] ?? '') === 'twitter' ? 'selected' : '' ?>>Twitter</option>
        <option value="discord" <?= ($filters['type'] ?? '') === 'discord' ? 'selected' : '' ?>>Discord</option>
    </select>
    <input type="text" name="search" placeholder="Search..." value="<?= e($filters['search'] ?? '') ?>">
    <button type="submit" class="btn-admin btn-admin-primary btn-admin-sm"><i class="bi bi-search"></i> Filter</button>
</form>

<!-- Blacklist Table -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>Blacklist Entries (<?= $result['total'] ?>)</h3>
    </div>
    <div class="admin-card-body" style="padding: 0; overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr><th>Type</th><th>Value</th><th>Reason</th><th>Date</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php if (empty($result['data'])): ?>
                    <tr><td colspan="5" style="text-align: center; color: var(--admin-text-muted); padding: 30px;">No blacklist entries.</td></tr>
                <?php else: foreach ($result['data'] as $entry): ?>
                <tr>
                    <td><span class="badge badge-dark"><?= e($entry['type']) ?></span></td>
                    <td><code><?= e($entry['value']) ?></code></td>
                    <td style="color: var(--admin-text-muted);"><?= e($entry['reason'] ?? '—') ?></td>
                    <td style="font-size: 0.85rem;"><?= e(format_date($entry['created_at'], 'M j, Y')) ?></td>
                    <td>
                        <form method="POST" style="display: inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="blacklist_id" value="<?= (int)$entry['id'] ?>">
                            <button class="btn-admin btn-admin-danger btn-admin-sm btn-confirm-delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean();
include VIEWS_PATH . '/layouts/admin.php';
?>
