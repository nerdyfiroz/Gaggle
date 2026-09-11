<?php
/**
 * Gaggle NFT — Application Service
 * 
 * Orchestrates the full whitelist application submission pipeline.
 */

require_once APP_PATH . '/models/Application.php';
require_once APP_PATH . '/models/Task.php';
require_once APP_PATH . '/models/Blacklist.php';
require_once APP_PATH . '/models/BlockedIp.php';
require_once APP_PATH . '/services/ValidationService.php';
require_once APP_PATH . '/services/RateLimitService.php';
require_once APP_PATH . '/services/CaptchaService.php';

class ApplicationService {
    private Application $appModel;
    private Task $taskModel;
    private Blacklist $blacklist;
    private BlockedIp $blockedIp;
    private ValidationService $validator;
    private RateLimitService $rateLimiter;
    private CaptchaService $captcha;

    public function __construct() {
        $this->appModel    = new Application();
        $this->taskModel   = new Task();
        $this->blacklist   = new Blacklist();
        $this->blockedIp   = new BlockedIp();
        $this->validator   = new ValidationService();
        $this->rateLimiter = new RateLimitService();
        $this->captcha     = new CaptchaService();
    }

    /**
     * Process a whitelist application submission.
     * Returns ['success' => bool, 'message' => string, 'application' => ?array, 'errors' => ?array]
     */
    public function submit(array $postData): array {
        $ip = get_client_ip();
        $db = Database::getConnection();

        // 1. Check if applications are enabled
        if (get_setting('applications_enabled', 'application', '1') !== '1') {
            return $this->error('Whitelist applications are currently closed.');
        }

        // 2. Check IP blocking
        if (get_setting('ip_blocking_enabled', 'security', '1') === '1') {
            if ($this->blockedIp->isBlocked($ip)) {
                return $this->error('Your IP address has been blocked from submitting applications.');
            }
        }

        // 3. Sanitize input
        $data = $this->sanitize($postData);
        $data['ip_address'] = $ip;
        $data['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // 4. Validate fields
        $errors = $this->validator->validateApplication($data);
        if (!empty($errors)) {
            if (isset($errors['bot'])) {
                // Silent rejection for bots
                return $this->error('Submission rejected.');
            }
            return ['success' => false, 'message' => 'Please fix the errors below.', 'errors' => $errors];
        }

        // 5. Verify CAPTCHA
        $captchaToken = $postData['cf-turnstile-response'] ?? $postData['g-recaptcha-response'] ?? null;
        if (!$this->captcha->verify($captchaToken)) {
            return $this->error('CAPTCHA verification failed. Please try again.');
        }

        // 6. Check CSRF
        if (get_setting('csrf_enabled', 'security', '1') === '1') {
            if (!verify_csrf($postData['_csrf_token'] ?? null)) {
                return $this->error('Invalid security token. Please refresh the page and try again.');
            }
        }

        // 7. Check blacklist
        $blacklistResult = $this->blacklist->checkApplication($data);
        if ($blacklistResult) {
            return $this->error($blacklistResult);
        }

        // 8. Check rate limits
        $shortTermError = $this->rateLimiter->checkShortTerm($ip);
        if ($shortTermError) {
            return $this->error($shortTermError);
        }

        $lifetimeError = $this->rateLimiter->checkLifetime($ip);
        if ($lifetimeError) {
            return $this->error($lifetimeError);
        }

        // 9. Check duplicates
        $duplicateWalletProtection = get_setting('duplicate_wallet_protection', 'application', '1') === '1';
        if ($duplicateWalletProtection && $this->appModel->walletExists($data['wallet_address'])) {
            return $this->error('This wallet address has already been submitted.');
        }
        if (get_setting('duplicate_twitter_protection', 'application', '0') === '1'
            && !empty($data['twitter_username']) && $this->appModel->twitterExists($data['twitter_username'])) {
            return $this->error('This Twitter/X account has already been used for an application.');
        }
        if (get_setting('duplicate_discord_protection', 'application', '0') === '1'
            && !empty($data['discord_username']) && $this->appModel->discordExists($data['discord_username'])) {
            return $this->error('This Discord account has already been used for an application.');
        }

        // 10. Validate task IDs against the currently enabled task set.
        $requiredTasks = $this->taskModel->getRequired();
        $completedTaskIds = $postData['tasks'] ?? [];
        if (!is_array($completedTaskIds)) $completedTaskIds = [];
        $enabledTasks = $this->taskModel->getEnabled();
        $enabledTaskIds = array_map('intval', array_column($enabledTasks, 'id'));
        $completedTaskIds = array_values(array_unique(array_map('intval', $completedTaskIds)));

        if (array_diff($completedTaskIds, $enabledTaskIds)) {
            return $this->error('One or more selected tasks are no longer available. Please refresh and try again.');
        }

        foreach ($requiredTasks as $task) {
            if (!in_array((int) $task['id'], $completedTaskIds, true)) {
                return $this->error('Please complete all required tasks before submitting.');
            }
        }

        // 11. Atomically consume the rate-limit event and persist the application/tasks.
        try {
            $db->beginTransaction();
            $this->rateLimiter->record($ip);
            $application = $this->appModel->create($data);

            if (!$application) {
                return $this->error('Failed to submit application. Please try again.');
            }

            if (!empty($completedTaskIds)) {
                $this->appModel->storeTasks($application['id'], $completedTaskIds);
            }
            $db->commit();

            regenerate_csrf();

            return [
                'success'     => true,
                'message'     => 'Application submitted successfully!',
                'application' => $application,
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Application submission error: ' . $e->getMessage());
            return $this->error('An error occurred while processing your application. Please try again.');
        }
    }

    /**
     * Sanitize form data.
     */
    private function sanitize(array $data): array {
        return [
            'wallet_address'     => $this->validator->sanitizeWallet($data['wallet_address'] ?? ''),
            'twitter_username'   => !empty($data['twitter_username']) ? $this->validator->sanitizeTwitter($data['twitter_username']) : null,
            'discord_username'   => !empty($data['discord_username']) ? $this->validator->sanitizeDiscord($data['discord_username']) : null,
            'telegram_username'  => !empty($data['telegram_username']) ? $this->validator->sanitizeTelegram($data['telegram_username']) : null,
            'email'              => !empty($data['email']) ? strtolower(trim($data['email'])) : null,
            'website_url'         => trim((string) ($data['website_url'] ?? '')),
        ];
    }

    /**
     * Create error response.
     */
    private function error(string $message): array {
        return ['success' => false, 'message' => $message, 'errors' => []];
    }
}
