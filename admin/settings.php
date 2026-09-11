<?php
/**
 * Gaggle NFT — Admin Settings
 */
require_once dirname(__DIR__) . '/app/middleware/AuthMiddleware.php';
require_admin_auth();

require_once APP_PATH . '/models/Setting.php';

$settingModel = new Setting();
$log = new AdminLog();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group = sanitize($_POST['group'] ?? 'general');
    $settings = $_POST['settings'] ?? [];

    $allowed = [
        'general' => ['project_name', 'project_tagline', 'project_description', 'twitter_url', 'discord_url', 'telegram_url', 'website_url', 'mint_date', 'supply', 'mint_price', 'blockchain', 'wl_spots'],
        'application' => ['applications_enabled', 'duplicate_wallet_protection', 'duplicate_twitter_protection', 'duplicate_discord_protection', 'require_email', 'require_discord', 'require_twitter', 'require_telegram', 'show_email_field', 'show_telegram_field', 'ip_application_limit', 'rate_limit_window_minutes', 'rate_limit_max_requests'],
        'captcha' => ['captcha_enabled', 'captcha_provider', 'captcha_site_key', 'captcha_secret_key'],
        'security' => ['session_timeout', 'csrf_enabled', 'ip_blocking_enabled', 'honeypot_enabled'],
    ];
    if (!isset($allowed[$group])) redirect('/admin/settings.php');
    foreach ($settings as $key => $value) {
        $key = sanitize($key);
        if (!in_array($key, $allowed[$group], true)) continue;
        $value = sanitize($value);
        if (in_array($key, ['applications_enabled', 'duplicate_wallet_protection', 'duplicate_twitter_protection', 'duplicate_discord_protection', 'require_email', 'require_discord', 'require_twitter', 'require_telegram', 'show_email_field', 'show_telegram_field', 'captcha_enabled', 'csrf_enabled', 'ip_blocking_enabled', 'honeypot_enabled'], true)) {
            $value = $value === '1' ? '1' : '0';
        }
        if (in_array($key, ['ip_application_limit', 'rate_limit_window_minutes', 'rate_limit_max_requests', 'session_timeout'], true)) {
            $value = (string) max(1, min(1000000, (int) $value));
        }
        if ($group === 'captcha' && $key === 'captcha_provider' && !in_array($value, ['turnstile', 'recaptcha'], true)) continue;
        $settingModel->set($key, $value, $group);
    }

    $log->log('update_settings', 'settings', $group);
    flash('success', ucfirst($group) . ' settings updated.');
    redirect('/admin/settings.php?tab=' . $group);
}

$general = $settingModel->getGroup('general');
$application = $settingModel->getGroup('application');
$captcha = $settingModel->getGroup('captcha');
$security = $settingModel->getGroup('security');
$activeTab = sanitize($_GET['tab'] ?? 'general');

$pageTitle = 'Settings';
ob_start();
?>

<!-- Tabs -->
<div class="admin-tabs">
    <button class="admin-tab <?= $activeTab === 'general' ? 'active' : '' ?>" data-tab="tab-general">General</button>
    <button class="admin-tab <?= $activeTab === 'application' ? 'active' : '' ?>" data-tab="tab-application">Application</button>
    <button class="admin-tab <?= $activeTab === 'captcha' ? 'active' : '' ?>" data-tab="tab-captcha">CAPTCHA</button>
    <button class="admin-tab <?= $activeTab === 'security' ? 'active' : '' ?>" data-tab="tab-security">Security</button>
</div>

<!-- General -->
<div class="tab-panel <?= $activeTab === 'general' ? 'active' : '' ?>" id="tab-general">
    <div class="admin-card">
        <div class="admin-card-body">
            <form method="POST" class="admin-form">
                <?= csrf_field() ?>
                <input type="hidden" name="group" value="general">
                <?php
                $fields = [
                    'project_name' => ['Project Name', 'text'],
                    'project_tagline' => ['Tagline', 'text'],
                    'project_description' => ['Description', 'textarea'],
                    'twitter_url' => ['Twitter/X URL', 'url'],
                    'discord_url' => ['Discord URL', 'url'],
                    'telegram_url' => ['Telegram URL', 'url'],
                    'website_url' => ['Website URL', 'url'],
                    'mint_date' => ['Mint Date', 'text'],
                    'supply' => ['Supply', 'text'],
                    'mint_price' => ['Mint Price', 'text'],
                    'blockchain' => ['Blockchain', 'text'],
                    'wl_spots' => ['WL Spots', 'text'],
                ];
                foreach ($fields as $key => [$label, $type]):
                ?>
                <div class="form-group">
                    <label><?= e($label) ?></label>
                    <?php if ($type === 'textarea'): ?>
                        <textarea name="settings[<?= e($key) ?>]" rows="3"><?= e($general[$key] ?? '') ?></textarea>
                    <?php else: ?>
                        <input type="<?= $type ?>" name="settings[<?= e($key) ?>]" value="<?= e($general[$key] ?? '') ?>">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <button type="submit" class="btn-admin btn-admin-primary"><i class="bi bi-check-lg"></i> Save General Settings</button>
            </form>
        </div>
    </div>
</div>

<!-- Application -->
<div class="tab-panel <?= $activeTab === 'application' ? 'active' : '' ?>" id="tab-application">
    <div class="admin-card">
        <div class="admin-card-body">
            <form method="POST" class="admin-form">
                <?= csrf_field() ?>
                <input type="hidden" name="group" value="application">
                <?php
                $toggles = [
                    'applications_enabled' => 'Applications Enabled',
                    'duplicate_wallet_protection' => 'Duplicate Wallet Protection',
                    'duplicate_twitter_protection' => 'Duplicate Twitter/X Protection',
                    'duplicate_discord_protection' => 'Duplicate Discord Protection',
                    'require_email' => 'Require Email',
                    'require_discord' => 'Require Discord',
                    'require_twitter' => 'Require Twitter/X',
                    'require_telegram' => 'Require Telegram',
                    'show_email_field' => 'Show Email Field',
                    'show_telegram_field' => 'Show Telegram Field',
                ];
                foreach ($toggles as $key => $label):
                ?>
                <div class="form-group" style="display: flex; justify-content: space-between; align-items: center;">
                    <label style="margin-bottom: 0;"><?= e($label) ?></label>
                    <select name="settings[<?= e($key) ?>]" style="width: 100px;">
                        <option value="1" <?= ($application[$key] ?? '0') === '1' ? 'selected' : '' ?>>Yes</option>
                        <option value="0" <?= ($application[$key] ?? '0') === '0' ? 'selected' : '' ?>>No</option>
                    </select>
                </div>
                <?php endforeach; ?>

                <hr style="border-color: var(--admin-border); margin: 20px 0;">

                <div class="form-group">
                    <label>IP Application Limit</label>
                    <input type="number" name="settings[ip_application_limit]" value="<?= e($application['ip_application_limit'] ?? '100') ?>" min="1">
                    <small style="color: var(--admin-text-muted);">Max applications per IP address (default: 100)</small>
                </div>
                <div class="form-group">
                    <label>Rate Limit Window (minutes)</label>
                    <input type="number" name="settings[rate_limit_window_minutes]" value="<?= e($application['rate_limit_window_minutes'] ?? '10') ?>" min="1">
                </div>
                <div class="form-group">
                    <label>Rate Limit Max Requests</label>
                    <input type="number" name="settings[rate_limit_max_requests]" value="<?= e($application['rate_limit_max_requests'] ?? '5') ?>" min="1">
                    <small style="color: var(--admin-text-muted);">Max submissions within the rate limit window</small>
                </div>
                <button type="submit" class="btn-admin btn-admin-primary"><i class="bi bi-check-lg"></i> Save Application Settings</button>
            </form>
        </div>
    </div>
</div>

<!-- CAPTCHA -->
<div class="tab-panel <?= $activeTab === 'captcha' ? 'active' : '' ?>" id="tab-captcha">
    <div class="admin-card">
        <div class="admin-card-body">
            <form method="POST" class="admin-form">
                <?= csrf_field() ?>
                <input type="hidden" name="group" value="captcha">
                <div class="form-group" style="display: flex; justify-content: space-between; align-items: center;">
                    <label style="margin-bottom: 0;">CAPTCHA Enabled</label>
                    <select name="settings[captcha_enabled]" style="width: 100px;">
                        <option value="1" <?= ($captcha['captcha_enabled'] ?? '0') === '1' ? 'selected' : '' ?>>Yes</option>
                        <option value="0" <?= ($captcha['captcha_enabled'] ?? '0') === '0' ? 'selected' : '' ?>>No</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Provider</label>
                    <select name="settings[captcha_provider]">
                        <option value="turnstile" <?= ($captcha['captcha_provider'] ?? '') === 'turnstile' ? 'selected' : '' ?>>Cloudflare Turnstile</option>
                        <option value="recaptcha" <?= ($captcha['captcha_provider'] ?? '') === 'recaptcha' ? 'selected' : '' ?>>Google reCAPTCHA</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Site Key</label>
                    <input type="text" name="settings[captcha_site_key]" value="<?= e($captcha['captcha_site_key'] ?? '') ?>" placeholder="Public site key">
                </div>
                <div class="form-group">
                    <label>Secret Key</label>
                    <input type="password" name="settings[captcha_secret_key]" value="<?= e($captcha['captcha_secret_key'] ?? '') ?>" placeholder="Secret key (server-side)">
                    <small style="color: var(--admin-text-muted);">This value is stored in the database. For extra security, use the .env file instead.</small>
                </div>
                <button type="submit" class="btn-admin btn-admin-primary"><i class="bi bi-check-lg"></i> Save CAPTCHA Settings</button>
            </form>
        </div>
    </div>
</div>

<!-- Security -->
<div class="tab-panel <?= $activeTab === 'security' ? 'active' : '' ?>" id="tab-security">
    <div class="admin-card">
        <div class="admin-card-body">
            <form method="POST" class="admin-form">
                <?= csrf_field() ?>
                <input type="hidden" name="group" value="security">
                <div class="form-group">
                    <label>Session Timeout (seconds)</label>
                    <input type="number" name="settings[session_timeout]" value="<?= e($security['session_timeout'] ?? '3600') ?>" min="300">
                </div>
                <?php
                $secToggles = [
                    'csrf_enabled' => 'CSRF Protection',
                    'ip_blocking_enabled' => 'IP Blocking',
                    'honeypot_enabled' => 'Honeypot Field',
                ];
                foreach ($secToggles as $key => $label):
                ?>
                <div class="form-group" style="display: flex; justify-content: space-between; align-items: center;">
                    <label style="margin-bottom: 0;"><?= e($label) ?></label>
                    <select name="settings[<?= e($key) ?>]" style="width: 100px;">
                        <option value="1" <?= ($security[$key] ?? '1') === '1' ? 'selected' : '' ?>>Enabled</option>
                        <option value="0" <?= ($security[$key] ?? '1') === '0' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                </div>
                <?php endforeach; ?>
                <button type="submit" class="btn-admin btn-admin-primary"><i class="bi bi-check-lg"></i> Save Security Settings</button>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include VIEWS_PATH . '/layouts/admin.php';
?>
