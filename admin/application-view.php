<?php
/**
 * Gaggle NFT — Admin Application Detail View
 */
require_once dirname(__DIR__) . '/app/middleware/AuthMiddleware.php';
require_admin_auth();

require_once APP_PATH . '/models/Application.php';
require_once APP_PATH . '/models/Blacklist.php';

$appModel = new Application();
$id = (int) ($_GET['id'] ?? 0);
$app = $appModel->getWithTasks($id);

if (!$app) {
    flash('error', 'Application not found.');
    redirect('/admin/applications.php');
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $log = new AdminLog();

    switch ($action) {
        case 'update_status':
            $newStatus = sanitize($_POST['status'] ?? '');
            if ($appModel->updateStatus($id, $newStatus)) {
                $log->log('status_change', 'application', $app['application_id'], "Changed to: {$newStatus}");
                flash('success', "Application status updated to {$newStatus}.");
            }
            break;

        case 'add_note':
            $note = sanitize($_POST['note'] ?? '');
            if (!empty($note) && $appModel->addNote($id, $note)) {
                $log->log('add_note', 'application', $app['application_id']);
                flash('success', 'Note added.');
            }
            break;

        case 'blacklist_wallet':
            $blacklist = new Blacklist();
            $blacklist->add('wallet', $app['wallet_address'], 'Blacklisted from application view');
            $appModel->updateStatus($id, 'blacklisted');
            $log->log('blacklist_wallet', 'application', $app['application_id'], $app['wallet_address']);
            flash('success', 'Wallet blacklisted.');
            break;

        case 'delete':
            $appId = $app['application_id'];
            $appModel->delete($id);
            $log->log('delete_application', 'application', $appId);
            flash('success', 'Application deleted.');
            redirect('/admin/applications.php');
            break;
    }

    redirect("/admin/application-view.php?id={$id}");
}

// Reload after POST
$app = $appModel->getWithTasks($id);

// Get other apps from same IP
$sameIp = $appModel->getAll(['ip' => $app['ip_address']], 1, 5);

$pageTitle = 'Application ' . $app['application_id'];
ob_start();
?>

<div style="margin-bottom: 16px;">
    <a href="/admin/applications.php" class="btn-admin btn-admin-secondary btn-admin-sm"><i class="bi bi-arrow-left"></i> Back to Applications</a>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
    <!-- Main Details -->
    <div>
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Application Details</h3>
                <span style="font-family: monospace; color: var(--admin-gold);"><?= e($app['application_id']) ?></span>
            </div>
            <div class="admin-card-body">
                <table class="admin-table" style="margin-bottom: 0;">
                    <tr><td style="width: 140px; font-weight: 600;">Status</td><td><?= status_badge($app['status']) ?></td></tr>
                    <tr><td style="font-weight: 600;">Wallet</td><td><code><?= e($app['wallet_address']) ?></code></td></tr>
                    <tr><td style="font-weight: 600;">Twitter/X</td><td><?= $app['twitter_username'] ? '@' . e($app['twitter_username']) : '—' ?></td></tr>
                    <tr><td style="font-weight: 600;">Discord</td><td><?= e($app['discord_username'] ?? '—') ?></td></tr>
                    <tr><td style="font-weight: 600;">Telegram</td><td><?= $app['telegram_username'] ? '@' . e($app['telegram_username']) : '—' ?></td></tr>
                    <tr><td style="font-weight: 600;">Email</td><td><?= e($app['email'] ?? '—') ?></td></tr>
                    <tr><td style="font-weight: 600;">IP Address</td><td><?= e($app['ip_address']) ?></td></tr>
                    <tr><td style="font-weight: 600;">Submitted</td><td><?= e(format_date($app['created_at'])) ?></td></tr>
                    <tr><td style="font-weight: 600;">Updated</td><td><?= e(format_date($app['updated_at'])) ?></td></tr>
                </table>
            </div>
        </div>

        <!-- Completed Tasks -->
        <?php if (!empty($app['tasks'])): ?>
        <div class="admin-card">
            <div class="admin-card-header"><h3>Completed Tasks</h3></div>
            <div class="admin-card-body" style="padding: 0;">
                <table class="admin-table">
                    <thead><tr><th>Task</th><th>Type</th><th>Completed</th></tr></thead>
                    <tbody>
                        <?php foreach ($app['tasks'] as $task): ?>
                        <tr>
                            <td><?= e($task['title']) ?></td>
                            <td><span class="badge badge-info"><?= e(task_type_label($task['type'])) ?></span></td>
                            <td><i class="bi bi-check-circle" style="color: var(--admin-primary);"></i></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Admin Notes -->
        <div class="admin-card">
            <div class="admin-card-header"><h3>Admin Notes</h3></div>
            <div class="admin-card-body">
                <?php if (!empty($app['admin_notes'])): ?>
                    <div class="notes-area"><?= e($app['admin_notes']) ?></div>
                <?php endif; ?>
                <form method="POST" class="admin-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_note">
                    <textarea name="note" placeholder="Add a note..." rows="3" style="margin-bottom: 8px;"></textarea>
                    <button type="submit" class="btn-admin btn-admin-primary btn-admin-sm"><i class="bi bi-plus"></i> Add Note</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Sidebar Actions -->
    <div>
        <!-- Status Actions -->
        <div class="admin-card">
            <div class="admin-card-header"><h3>Actions</h3></div>
            <div class="admin-card-body">
                <form method="POST" style="display: flex; flex-direction: column; gap: 8px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_status">
                    
                    <?php if ($app['status'] !== 'approved'): ?>
                        <button name="status" value="approved" class="btn-admin btn-admin-primary" style="width: 100%; justify-content: center;">
                            <i class="bi bi-check-circle"></i> Approve
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($app['status'] !== 'rejected'): ?>
                        <button name="status" value="rejected" class="btn-admin btn-admin-danger" style="width: 100%; justify-content: center;">
                            <i class="bi bi-x-circle"></i> Reject
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($app['status'] !== 'review'): ?>
                        <button name="status" value="review" class="btn-admin btn-admin-secondary" style="width: 100%; justify-content: center;">
                            <i class="bi bi-eye"></i> Put in Review
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($app['status'] !== 'pending'): ?>
                        <button name="status" value="pending" class="btn-admin btn-admin-secondary" style="width: 100%; justify-content: center;">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset to Pending
                        </button>
                    <?php endif; ?>
                </form>

                <hr style="border-color: var(--admin-border); margin: 16px 0;">

                <form method="POST" style="display: flex; flex-direction: column; gap: 8px;">
                    <?= csrf_field() ?>
                    <button name="action" value="blacklist_wallet" class="btn-admin btn-admin-secondary" style="width: 100%; justify-content: center;" onclick="return confirm('Blacklist this wallet?')">
                        <i class="bi bi-shield-x"></i> Blacklist Wallet
                    </button>
                    <button name="action" value="delete" class="btn-admin btn-admin-danger" style="width: 100%; justify-content: center;" onclick="return confirm('Delete this application permanently?')">
                        <i class="bi bi-trash"></i> Delete Application
                    </button>
                </form>
            </div>
        </div>

        <!-- Same IP -->
        <?php if ($sameIp['total'] > 1): ?>
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Same IP (<?= $sameIp['total'] ?>)</h3>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <table class="admin-table">
                    <?php foreach ($sameIp['data'] as $sip): if ($sip['id'] == $app['id']) continue; ?>
                    <tr>
                        <td><a href="/admin/application-view.php?id=<?= (int)$sip['id'] ?>"><?= e($sip['application_id']) ?></a></td>
                        <td><?= status_badge($sip['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include VIEWS_PATH . '/layouts/admin.php';
?>
