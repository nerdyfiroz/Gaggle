<?php
/**
 * Gaggle NFT — Application Success Page
 */
define('GAGGLE_ROOT', dirname(__DIR__));
require_once GAGGLE_ROOT . '/app/config/config.php';
require_once APP_PATH . '/models/Application.php';

header('Cache-Control: no-store, private');

$applicationId = $_GET['id'] ?? '';
$application = null;

if (preg_match('/^WL-[A-F0-9]{8}$/i', $applicationId)) {
    $appModel = new Application();
    $application = $appModel->findByApplicationId(sanitize($applicationId));
}

$pageTitle = 'Application Submitted';
ob_start();
?>

<div class="success-container">
    <?php if ($application): ?>
        <div class="success-icon">🦆</div>
        <h1 class="section-subtitle" style="font-size: 2rem;">Application Submitted!</h1>
        <p class="text-muted" style="margin-top: 12px;">
            Your whitelist application has been received. Save your application ID below.
        </p>

        <div class="success-details">
            <div class="success-detail-row">
                <span class="success-detail-label">Application ID</span>
                <span class="success-detail-value" style="color: var(--gaggle-gold); font-family: var(--font-pixel); font-size: 0.8rem;">
                    <?= e($application['application_id']) ?>
                </span>
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

        <div class="alert alert-warning" style="margin-top: 24px;">
            <i class="bi bi-bookmark"></i> <strong>Save your Application ID!</strong><br>
            You'll need it to check your whitelist status. Bookmark this page or write it down.
        </div>

        <div style="margin-top: 32px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
            <a href="/status.php" class="btn-gaggle btn-secondary btn-sm">
                <i class="bi bi-search"></i> Check Status
            </a>
            <a href="/" class="btn-gaggle btn-primary btn-sm">
                <i class="bi bi-house"></i> Back to Home
            </a>
        </div>
    <?php else: ?>
        <div class="success-icon" style="border-color: var(--gaggle-crimson-light);">❌</div>
        <h1 class="section-subtitle" style="font-size: 2rem;">Application Not Found</h1>
        <p class="text-muted" style="margin-top: 12px;">
            We couldn't find an application with that ID. Please check and try again.
        </p>
        <div style="margin-top: 32px;">
            <a href="/" class="btn-gaggle btn-primary">Back to Home</a>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include VIEWS_PATH . '/layouts/public.php';
?>
