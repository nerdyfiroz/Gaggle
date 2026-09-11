<?php
/**
 * Gaggle NFT — Application Status Check
 */
define('GAGGLE_ROOT', dirname(__DIR__));
require_once GAGGLE_ROOT . '/app/config/config.php';
require_once APP_PATH . '/models/Application.php';
require_once APP_PATH . '/services/RateLimitService.php';

header('Cache-Control: no-store, private');

$application = null;
$searched = false;
$lookupRateLimited = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['id'])) {
    $searched = true;
    $rateLimiter = new RateLimitService();
    if (!$rateLimiter->checkEndpoint(get_client_ip(), 'status_lookup', 10, 30)) {
        $rateLimiter->record(get_client_ip(), 'status_lookup');
        $applicationId = strtoupper(trim((string) $_GET['id']));
        if (preg_match('/^WL-[A-F0-9]{8}$/', $applicationId)) {
            $appModel = new Application();
            $application = $appModel->findByApplicationId($applicationId);
        }
    } else {
        $lookupRateLimited = true;
    }
}

$pageTitle = 'Check Application Status';
ob_start();
?>

<div class="apply-page">
    <div class="apply-container" style="max-width: 600px;">
        <div class="apply-header">
            <p class="section-title">Status</p>
            <h1 class="section-subtitle">Check Your Application</h1>
            <p class="section-desc" style="margin-bottom: 0;">Enter your application ID to check your whitelist status.</p>
        </div>

        <form method="GET" action="/status.php" style="margin-bottom: 32px;">
            <div class="form-group">
                <label class="form-label" for="app_id">Application ID</label>
                <div style="display: flex; gap: 12px;">
                    <input type="text" class="form-control-custom" id="app_id" name="id"
                           placeholder="WL-XXXXXXXX" value="<?= e($_GET['id'] ?? '') ?>" maxlength="20" required>
                    <button type="submit" class="btn-gaggle btn-primary" style="white-space: nowrap;">
                        <i class="bi bi-search"></i> Check
                    </button>
                </div>
            </div>
        </form>

        <?php if ($searched && $application): ?>
            <div class="success-details">
                <div class="success-detail-row">
                    <span class="success-detail-label">Application ID</span>
                    <span class="success-detail-value" style="color: var(--gaggle-gold);"><?= e($application['application_id']) ?></span>
                </div>
                <div class="success-detail-row">
                    <span class="success-detail-label">Wallet</span>
                    <span class="success-detail-value"><?= e(mask_wallet($application['wallet_address'])) ?></span>
                </div>
                <div class="success-detail-row">
                    <span class="success-detail-label">Submitted</span>
                    <span class="success-detail-value"><?= e(format_date($application['created_at'])) ?></span>
                </div>
                <div class="success-detail-row">
                    <span class="success-detail-label">Status</span>
                    <span class="success-detail-value"><?= status_badge($application['status']) ?></span>
                </div>
            </div>
        <?php elseif ($searched && $lookupRateLimited): ?>
            <div class="alert alert-error">
                <i class="bi bi-hourglass-split"></i> Too many status checks. Please wait a few minutes and try again.
            </div>
        <?php elseif ($searched): ?>
            <div class="alert alert-error">
                <i class="bi bi-exclamation-circle"></i> No application found with that ID. Please double-check and try again.
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 32px;">
            <a href="/" class="btn-gaggle btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Home</a>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include VIEWS_PATH . '/layouts/public.php';
?>
