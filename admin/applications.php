<?php
/**
 * Gaggle NFT — Admin Applications List
 */
require_once dirname(__DIR__) . '/app/middleware/AuthMiddleware.php';
require_admin_auth();

require_once APP_PATH . '/models/Application.php';

$appModel = new Application();

// Filters
$filters = [];
if (!empty($_GET['status'])) $filters['status'] = sanitize($_GET['status']);
if (!empty($_GET['search'])) $filters['search'] = sanitize($_GET['search']);
if (!empty($_GET['date_from'])) $filters['date_from'] = sanitize($_GET['date_from']);
if (!empty($_GET['date_to'])) $filters['date_to'] = sanitize($_GET['date_to']);

$page = max(1, (int) ($_GET['page'] ?? 1));
$result = $appModel->getAll($filters, $page, 25);

$pageTitle = 'Applications';
ob_start();
?>

<!-- Filter Bar -->
<form method="GET" class="filter-bar">
    <input type="text" name="search" placeholder="Search wallet, Twitter, Discord, IP..." 
           value="<?= e($filters['search'] ?? '') ?>" style="flex: 1; min-width: 200px;">
    <select name="status">
        <option value="">All Statuses</option>
        <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="approved" <?= ($filters['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
        <option value="rejected" <?= ($filters['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
        <option value="blacklisted" <?= ($filters['status'] ?? '') === 'blacklisted' ? 'selected' : '' ?>>Blacklisted</option>
        <option value="review" <?= ($filters['status'] ?? '') === 'review' ? 'selected' : '' ?>>Review</option>
    </select>
    <input type="date" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>" title="From date">
    <input type="date" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>" title="To date">
    <button type="submit" class="btn-admin btn-admin-primary btn-admin-sm"><i class="bi bi-search"></i> Filter</button>
    <a href="/admin/applications.php" class="btn-admin btn-admin-secondary btn-admin-sm">Clear</a>
</form>

<!-- Bulk Actions -->
<div class="bulk-actions" id="bulk-actions">
    <span class="bulk-count">0 selected</span>
    <button class="btn-admin btn-admin-primary btn-admin-sm btn-bulk-action" data-action="approve"><i class="bi bi-check"></i> Approve</button>
    <button class="btn-admin btn-admin-secondary btn-admin-sm btn-bulk-action" data-action="reject"><i class="bi bi-x"></i> Reject</button>
    <button class="btn-admin btn-admin-secondary btn-admin-sm btn-bulk-action" data-action="blacklist"><i class="bi bi-shield-x"></i> Blacklist</button>
    <button class="btn-admin btn-admin-danger btn-admin-sm btn-bulk-action" data-action="delete"><i class="bi bi-trash"></i> Delete</button>
</div>

<!-- Applications Table -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>Applications (<?= number_format($result['total']) ?>)</h3>
    </div>
    <div class="admin-card-body" style="padding: 0; overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all"></th>
                    <th>ID</th>
                    <th>Wallet</th>
                    <th>Twitter</th>
                    <th>Discord</th>
                    <th>Status</th>
                    <th>IP</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($result['data'])): ?>
                    <tr><td colspan="9" style="text-align: center; padding: 40px; color: var(--admin-text-muted);">No applications found.</td></tr>
                <?php else: ?>
                    <?php foreach ($result['data'] as $app): ?>
                    <tr>
                        <td><input type="checkbox" class="row-checkbox" value="<?= (int)$app['id'] ?>"></td>
                        <td><a href="/admin/application-view.php?id=<?= (int)$app['id'] ?>"><?= e($app['application_id']) ?></a></td>
                        <td><code style="font-size: 0.8rem;"><?= e(mask_wallet($app['wallet_address'])) ?></code></td>
                        <td><?= $app['twitter_username'] ? '@' . e($app['twitter_username']) : '—' ?></td>
                        <td><?= $app['discord_username'] ? e($app['discord_username']) : '—' ?></td>
                        <td><?= status_badge($app['status']) ?></td>
                        <td style="font-size: 0.8rem;"><?= e($app['ip_address']) ?></td>
                        <td style="font-size: 0.85rem;" title="<?= e($app['created_at']) ?>"><?= e(time_ago($app['created_at'])) ?></td>
                        <td>
                            <div style="display: flex; gap: 4px;">
                                <?php if ($app['status'] !== 'approved'): ?>
                                    <button class="btn-admin btn-admin-primary btn-admin-sm btn-status-change" data-id="<?= (int)$app['id'] ?>" data-status="approved" title="Approve">✓</button>
                                <?php endif; ?>
                                <?php if ($app['status'] !== 'rejected'): ?>
                                    <button class="btn-admin btn-admin-danger btn-admin-sm btn-status-change" data-id="<?= (int)$app['id'] ?>" data-status="rejected" title="Reject">✗</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($result['total_pages'] > 1): ?>
<div class="pagination">
    <?php
    $queryString = http_build_query(array_filter($filters));
    $baseUrl = '/admin/applications.php?' . ($queryString ? $queryString . '&' : '');
    ?>
    <a href="<?= $baseUrl ?>page=1" class="<?= $result['page'] <= 1 ? 'disabled' : '' ?>">«</a>
    <a href="<?= $baseUrl ?>page=<?= max(1, $result['page'] - 1) ?>" class="<?= $result['page'] <= 1 ? 'disabled' : '' ?>">‹</a>
    
    <?php
    $start = max(1, $result['page'] - 2);
    $end = min($result['total_pages'], $result['page'] + 2);
    for ($i = $start; $i <= $end; $i++):
    ?>
        <a href="<?= $baseUrl ?>page=<?= $i ?>" class="<?= $i === $result['page'] ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    
    <a href="<?= $baseUrl ?>page=<?= min($result['total_pages'], $result['page'] + 1) ?>" class="<?= $result['page'] >= $result['total_pages'] ? 'disabled' : '' ?>">›</a>
    <a href="<?= $baseUrl ?>page=<?= $result['total_pages'] ?>" class="<?= $result['page'] >= $result['total_pages'] ? 'disabled' : '' ?>">»</a>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include VIEWS_PATH . '/layouts/admin.php';
?>
