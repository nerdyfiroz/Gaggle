<?php
/**
 * Gaggle NFT — CSV Export
 */
require_once dirname(__DIR__) . '/app/middleware/AuthMiddleware.php';
require_admin_auth();

require_once APP_PATH . '/models/Application.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appModel = new Application();
    $log = new AdminLog();

    $filters = [];
    if (!empty($_POST['status'])) $filters['status'] = sanitize($_POST['status']);
    if (!empty($_POST['date_from'])) $filters['date_from'] = sanitize($_POST['date_from']);
    if (!empty($_POST['date_to'])) $filters['date_to'] = sanitize($_POST['date_to']);
    if (!empty($_POST['search'])) $filters['search'] = sanitize($_POST['search']);

    $data = $appModel->getForExport($filters);

    // Log export
    $log->log('export_csv', 'applications', null, 'Status: ' . ($filters['status'] ?? 'all'));

    // Send CSV headers
    $filename = 'gaggle_applications_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // UTF-8 BOM for Excel
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Header row
    fputcsv($output, ['Application ID', 'Wallet', 'Twitter', 'Discord', 'Telegram', 'Email', 'IP', 'Status', 'Notes', 'Date']);

    // Data rows
    foreach ($data as $row) {
        fputcsv($output, [
            csv_safe($row['application_id']),
            csv_safe($row['wallet_address']),
            $row['twitter_username'] ?? '',
            $row['discord_username'] ?? '',
            $row['telegram_username'] ?? '',
            $row['email'] ?? '',
            $row['ip_address'],
            $row['status'],
            csv_safe($row['admin_notes'] ?? ''),
            $row['created_at'],
        ]);
    }

    fclose($output);
    exit;
}

function csv_safe(?string $value): string {
    $value = (string) $value;
    return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
}

$pageTitle = 'Export';
ob_start();
?>

<div class="admin-card" style="max-width: 500px;">
    <div class="admin-card-header"><h3>Export Applications as CSV</h3></div>
    <div class="admin-card-body">
        <form method="POST" class="admin-form">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Filter by Status</label>
                <select name="status">
                    <option value="">All Applications</option>
                    <option value="pending">Pending Only</option>
                    <option value="approved">Approved Only</option>
                    <option value="rejected">Rejected Only</option>
                    <option value="blacklisted">Blacklisted Only</option>
                </select>
            </div>
            <div class="form-group"><label>Search</label><input type="search" name="search" placeholder="Wallet, social, IP, or application ID"></div>
            <div class="form-group"><label>From date</label><input type="date" name="date_from"></div>
            <div class="form-group"><label>To date</label><input type="date" name="date_to"></div>
            <p style="color: var(--admin-text-muted); font-size: 0.9rem; margin-bottom: 16px;">
                The CSV will include: Application ID, Wallet, Twitter, Discord, Telegram, Email, IP, Status, Notes, Date.
            </p>
            <button type="submit" class="btn-admin btn-admin-primary">
                <i class="bi bi-download"></i> Export CSV
            </button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include VIEWS_PATH . '/layouts/admin.php';
?>
