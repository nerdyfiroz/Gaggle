<?php
/**
 * Gaggle NFT — Admin IP Management
 */
require_once dirname(__DIR__) . '/app/middleware/AuthMiddleware.php';
require_admin_auth();

require_once APP_PATH . '/models/BlockedIp.php';

$blockedIpModel = new BlockedIp();
$log = new AdminLog();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'block') {
        $ip = sanitize($_POST['ip'] ?? '');
        $reason = sanitize($_POST['reason'] ?? '');
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $blockedIpModel->block($ip, $reason);
            $log->log('block_ip', 'ip', $ip, $reason);
            flash('success', "IP {$ip} blocked.");
        } else {
            flash('error', 'Invalid IP address.');
        }
    } elseif ($action === 'unblock') {
        $id = (int) ($_POST['blocked_id'] ?? 0);
        $blockedIpModel->unblock($id);
        $log->log('unblock_ip', 'blocked_ip', (string) $id);
        flash('success', 'IP unblocked.');
    }

    redirect('/admin/ips.php');
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$search = sanitize($_GET['search'] ?? '');
$view = $_GET['view'] ?? 'stats';

$pageTitle = 'IP Management';
ob_start();
?>

<!-- Tabs -->
<div class="admin-tabs" style="margin-bottom: 20px;">
    <a href="/admin/ips.php?view=stats" class="admin-tab <?= $view === 'stats' ? 'active' : '' ?>">IP Statistics</a>
    <a href="/admin/ips.php?view=blocked" class="admin-tab <?= $view === 'blocked' ? 'active' : '' ?>">Blocked IPs</a>
</div>

<!-- Block IP Form -->
<form method="POST" class="admin-form" style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="block">
    <input type="text" name="ip" placeholder="IP address to block" required style="flex: 1; min-width: 200px; padding: 8px; background: var(--admin-bg); border: 1px solid var(--admin-border); border-radius: 8px; color: var(--admin-text);">
    <input type="text" name="reason" placeholder="Reason" style="width: 200px; padding: 8px; background: var(--admin-bg); border: 1px solid var(--admin-border); border-radius: 8px; color: var(--admin-text);">
    <button type="submit" class="btn-admin btn-admin-danger btn-admin-sm"><i class="bi bi-shield-x"></i> Block IP</button>
</form>

<?php if ($view === 'blocked'): ?>
    <?php $blocked = $blockedIpModel->getAll($page); ?>
    <div class="admin-card">
        <div class="admin-card-header"><h3>Blocked IPs (<?= $blocked['total'] ?>)</h3></div>
        <div class="admin-card-body" style="padding: 0; overflow-x: auto;">
            <table class="admin-table">
                <thead><tr><th>IP Address</th><th>Apps</th><th>Reason</th><th>Blocked</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($blocked['data'] as $entry): ?>
                    <tr>
                        <td><code><?= e($entry['ip_address']) ?></code></td>
                        <td><?= (int)($entry['app_count'] ?? 0) ?></td>
                        <td style="color: var(--admin-text-muted);"><?= e($entry['reason'] ?? '—') ?></td>
                        <td style="font-size: 0.85rem;"><?= e(format_date($entry['blocked_at'], 'M j, Y')) ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="unblock">
                                <input type="hidden" name="blocked_id" value="<?= (int)$entry['id'] ?>">
                                <button class="btn-admin btn-admin-primary btn-admin-sm"><i class="bi bi-unlock"></i> Unblock</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <form method="GET" class="filter-bar" style="margin-bottom: 12px;">
        <input type="hidden" name="view" value="stats">
        <input type="text" name="search" placeholder="Search IP..." value="<?= e($search) ?>">
        <button type="submit" class="btn-admin btn-admin-primary btn-admin-sm"><i class="bi bi-search"></i></button>
    </form>

    <?php $ipStats = $blockedIpModel->getIpStats($page, 25, $search ?: null); ?>
    <div class="admin-card">
        <div class="admin-card-header"><h3>IP Statistics (<?= $ipStats['total'] ?> unique IPs)</h3></div>
        <div class="admin-card-body" style="padding: 0; overflow-x: auto;">
            <table class="admin-table">
                <thead><tr><th>IP Address</th><th>Applications</th><th>First Seen</th><th>Last Seen</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($ipStats['data'] as $row): ?>
                    <tr>
                        <td><code><?= e($row['ip_address']) ?></code></td>
                        <td><?= (int)$row['app_count'] ?></td>
                        <td style="font-size: 0.85rem;"><?= e(format_date($row['first_submission'], 'M j, Y')) ?></td>
                        <td style="font-size: 0.85rem;"><?= e(format_date($row['last_submission'], 'M j, Y')) ?></td>
                        <td><?= $row['is_blocked'] ? '<span class="badge badge-danger">Blocked</span>' : '<span class="badge badge-success">Active</span>' ?></td>
                        <td>
                            <a href="/admin/applications.php?search=<?= urlencode($row['ip_address']) ?>" class="btn-admin btn-admin-secondary btn-admin-sm"><i class="bi bi-eye"></i></a>
                            <?php if (!$row['is_blocked']): ?>
                                <form method="POST" style="display: inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="block">
                                    <input type="hidden" name="ip" value="<?= e($row['ip_address']) ?>">
                                    <input type="hidden" name="reason" value="Blocked from IP management">
                                    <button class="btn-admin btn-admin-danger btn-admin-sm"><i class="bi bi-shield-x"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include VIEWS_PATH . '/layouts/admin.php';
?>
