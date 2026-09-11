<?php
/**
 * Gaggle NFT — Whitelist Application Page
 */
define('GAGGLE_ROOT', dirname(__DIR__));
require_once GAGGLE_ROOT . '/app/config/config.php';
require_once APP_PATH . '/models/Task.php';
require_once APP_PATH . '/services/CaptchaService.php';

// Handle POST submission via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once APP_PATH . '/services/ApplicationService.php';
    
    $service = new ApplicationService();
    $result = $service->submit($_POST);

    if (is_ajax()) {
        if ($result['success']) {
            json_response([
                'success'        => true,
                'message'        => $result['message'],
                'application_id' => $result['application']['application_id'],
                'redirect'       => '/success.php?id=' . urlencode($result['application']['application_id']),
            ]);
        } else {
            json_response([
                'success' => false,
                'message' => $result['message'],
                'errors'  => $result['errors'] ?? [],
            ], 422);
        }
    }

    // Non-AJAX fallback
    if ($result['success']) {
        redirect('/success.php?id=' . urlencode($result['application']['application_id']));
    }

    // Store errors for display
    $errors = $result['errors'] ?? [];
    $errorMessage = $result['message'] ?? 'Submission failed.';
    store_old($_POST);
}

// Load tasks
$taskModel = new Task();
$tasks = $taskModel->getEnabled();

// Load CAPTCHA config
$captcha = new CaptchaService();
$captchaEnabled = $captcha->isEnabled();
$captchaSiteKey = $captcha->getSiteKey();

// Settings
$appSettings = get_settings_group('application');
$requireEmail = ($appSettings['require_email'] ?? '0') === '1';
$requireDiscord = ($appSettings['require_discord'] ?? '1') === '1';
$requireTwitter = ($appSettings['require_twitter'] ?? '1') === '1';
$requireTelegram = ($appSettings['require_telegram'] ?? '0') === '1';
$showEmail = ($appSettings['show_email_field'] ?? '1') === '1';
$showTelegram = ($appSettings['show_telegram_field'] ?? '1') === '1';
$applicationsEnabled = ($appSettings['applications_enabled'] ?? '1') === '1';

$pageTitle = 'Apply for Whitelist';
$extraJs = ['/assets/js/apply.js'];

ob_start();
?>

<div class="apply-page">
    <div class="apply-container">
        <div class="apply-header">
            <p class="section-title">Whitelist</p>
            <h1 class="section-subtitle">Apply for Whitelist</h1>
            <p class="section-desc" style="margin-bottom: 0;">Complete the tasks, fill in your details, and submit your application.</p>
        </div>

        <?php if (!$applicationsEnabled): ?>
            <div class="alert alert-warning" style="text-align: center; max-width: 500px; margin: 0 auto;">
                <i class="bi bi-exclamation-triangle"></i> Whitelist applications are currently closed. Check back later.
            </div>
        <?php else: ?>

        <?php if (!empty($errorMessage) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="alert alert-error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <!-- Step Indicator -->
        <div class="steps-indicator">
            <div class="step-item active">
                <div class="step-number">1</div>
                <span class="step-label">Tasks</span>
            </div>
            <div class="step-connector"></div>
            <div class="step-item">
                <div class="step-number">2</div>
                <span class="step-label">Details</span>
            </div>
            <div class="step-connector"></div>
            <div class="step-item">
                <div class="step-number">3</div>
                <span class="step-label">Verify</span>
            </div>
            <div class="step-connector"></div>
            <div class="step-item">
                <div class="step-number">4</div>
                <span class="step-label">Submit</span>
            </div>
        </div>

        <form id="wl-application-form" action="/apply.php" method="POST" novalidate data-blockchain="<?= e(get_setting('blockchain', 'general', 'Ethereum')) ?>">
            <?= csrf_field() ?>

            <!-- Honeypot -->
            <div class="hp-field">
                <label for="website_url">Website</label>
                <input type="text" name="website_url" id="website_url" tabindex="-1" autocomplete="off">
            </div>

            <!-- STEP 1: Tasks -->
            <div class="form-step active" id="step-tasks">
                <h3 style="margin-bottom: 8px; font-size: 1.3rem;">Step 1 — Complete Tasks</h3>
                <p class="text-muted" style="margin-bottom: 24px;">Complete the required tasks below before proceeding.</p>

                <div class="task-list">
                    <?php foreach ($tasks as $task): ?>
                        <div class="task-card" data-required="<?= $task['required'] ? '1' : '0' ?>">
                            <div class="task-checkbox"></div>
                            <input type="checkbox" name="tasks[]" value="<?= (int)$task['id'] ?>" class="d-none" id="task-<?= (int)$task['id'] ?>">
                            <div class="task-info">
                                <div class="task-title-text">
                                    <?= e($task['title']) ?>
                                    <span class="task-badge <?= $task['required'] ? 'required' : 'optional' ?>">
                                        <?= $task['required'] ? 'Required' : 'Optional' ?>
                                    </span>
                                </div>
                                <div class="task-desc-text"><?= e($task['description']) ?></div>
                                <?php if (!empty($task['url'])): ?>
                                    <a href="<?= e($task['url']) ?>" target="_blank" rel="noopener" class="task-link" style="margin-top: 6px;">
                                        <i class="bi <?= e(task_type_icon($task['type'])) ?>"></i> Go <i class="bi bi-box-arrow-up-right" style="font-size: 0.75rem;"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="text-align: right;">
                    <button type="button" class="btn-gaggle btn-primary btn-next-step">
                        Next — Enter Details <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 2: Form Fields -->
            <div class="form-step" id="step-details">
                <h3 style="margin-bottom: 8px; font-size: 1.3rem;">Step 2 — Enter Your Information</h3>
                <p class="text-muted" style="margin-bottom: 24px;">Provide your wallet and social details.</p>

                <div class="form-group">
                    <label class="form-label" for="wallet_address">
                        Wallet Address <span class="required-star">*</span>
                    </label>
                    <input type="text" class="form-control-custom <?= isset($errors['wallet_address']) ? 'error' : '' ?>"
                           id="wallet_address" name="wallet_address" data-required="1"
                           placeholder="0x..." value="<?= e(old('wallet_address')) ?>" maxlength="255">
                    <div class="form-help">Your <?= e(get_setting('blockchain', 'general', 'Ethereum')) ?> wallet address</div>
                    <?php if (isset($errors['wallet_address'])): ?>
                        <div class="form-error"><?= e($errors['wallet_address']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="twitter_username">
                        Twitter/X Username <?= $requireTwitter ? '<span class="required-star">*</span>' : '' ?>
                    </label>
                    <input type="text" class="form-control-custom <?= isset($errors['twitter_username']) ? 'error' : '' ?>"
                           id="twitter_username" name="twitter_username" data-required="<?= $requireTwitter ? '1' : '0' ?>"
                           placeholder="@username" value="<?= e(old('twitter_username')) ?>" maxlength="100">
                    <?php if (isset($errors['twitter_username'])): ?>
                        <div class="form-error"><?= e($errors['twitter_username']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="discord_username">
                        Discord Username <?= $requireDiscord ? '<span class="required-star">*</span>' : '' ?>
                    </label>
                    <input type="text" class="form-control-custom <?= isset($errors['discord_username']) ? 'error' : '' ?>"
                           id="discord_username" name="discord_username" data-required="<?= $requireDiscord ? '1' : '0' ?>"
                           placeholder="username" value="<?= e(old('discord_username')) ?>" maxlength="100">
                    <?php if (isset($errors['discord_username'])): ?>
                        <div class="form-error"><?= e($errors['discord_username']) ?></div>
                    <?php endif; ?>
                </div>

                <?php if ($showTelegram): ?>
                <div class="form-group">
                    <label class="form-label" for="telegram_username">
                        Telegram Username <?= $requireTelegram ? '<span class="required-star">*</span>' : '' ?>
                    </label>
                    <input type="text" class="form-control-custom <?= isset($errors['telegram_username']) ? 'error' : '' ?>"
                           id="telegram_username" name="telegram_username" data-required="<?= $requireTelegram ? '1' : '0' ?>"
                           placeholder="@username" value="<?= e(old('telegram_username')) ?>" maxlength="100">
                    <?php if (isset($errors['telegram_username'])): ?>
                        <div class="form-error"><?= e($errors['telegram_username']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($showEmail): ?>
                <div class="form-group">
                    <label class="form-label" for="email">
                        Email Address <?= $requireEmail ? '<span class="required-star">*</span>' : '' ?>
                    </label>
                    <input type="email" class="form-control-custom <?= isset($errors['email']) ? 'error' : '' ?>"
                           id="email" name="email" data-required="<?= $requireEmail ? '1' : '0' ?>"
                           placeholder="you@email.com" value="<?= e(old('email')) ?>" maxlength="255">
                    <?php if (isset($errors['email'])): ?>
                        <div class="form-error"><?= e($errors['email']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                    <button type="button" class="btn-gaggle btn-secondary btn-prev-step">
                        <i class="bi bi-arrow-left"></i> Back
                    </button>
                    <button type="button" class="btn-gaggle btn-primary btn-next-step">
                        Next — Verify <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 3: CAPTCHA -->
            <div class="form-step" id="step-captcha">
                <h3 style="margin-bottom: 8px; font-size: 1.3rem;">Step 3 — Verify You're Human</h3>
                <p class="text-muted" style="margin-bottom: 24px;">Complete the verification below.</p>

                <div style="display: flex; justify-content: center; margin-bottom: 32px;">
                    <?php if ($captchaEnabled && !empty($captchaSiteKey)): ?>
                        <div class="cf-turnstile" data-sitekey="<?= e($captchaSiteKey) ?>" data-theme="dark"></div>
                        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                    <?php else: ?>
                        <div class="alert alert-success" style="max-width: 400px;">
                            <i class="bi bi-check-circle"></i> Verification not required at this time.
                        </div>
                    <?php endif; ?>
                </div>

                <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                    <button type="button" class="btn-gaggle btn-secondary btn-prev-step">
                        <i class="bi bi-arrow-left"></i> Back
                    </button>
                    <button type="button" class="btn-gaggle btn-primary btn-next-step">
                        Next — Review <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 4: Submit -->
            <div class="form-step" id="step-submit">
                <h3 style="margin-bottom: 8px; font-size: 1.3rem;">Step 4 — Review & Submit</h3>
                <p class="text-muted" style="margin-bottom: 24px;">Review your information and submit your application.</p>

                <div style="background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-md); padding: 24px; margin-bottom: 32px;">
                    <p style="color: var(--dark-text-muted); font-size: 0.95rem; margin-bottom: 12px;">
                        <i class="bi bi-info-circle"></i> By submitting this application, you confirm that you have completed all required tasks and the information you provided is accurate.
                    </p>
                    <p style="color: var(--dark-text-muted); font-size: 0.95rem;">
                        You will receive a unique application ID after submission. Save it to check your status later.
                    </p>
                </div>

                <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                    <button type="button" class="btn-gaggle btn-secondary btn-prev-step">
                        <i class="bi bi-arrow-left"></i> Back
                    </button>
                    <button type="submit" class="btn-gaggle btn-gold btn-submit">
                        <i class="bi bi-send-fill"></i> Submit Application
                    </button>
                </div>
            </div>
        </form>

        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
clear_old();
include VIEWS_PATH . '/layouts/public.php';
?>
