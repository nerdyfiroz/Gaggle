<?php
/**
 * Gaggle NFT — CAPTCHA Verification Service
 * 
 * Supports Cloudflare Turnstile with extensible provider pattern.
 */

class CaptchaService {

    /**
     * Check if CAPTCHA is enabled.
     */
    public function isEnabled(): bool {
        return get_setting('captcha_enabled', 'captcha', '0') === '1';
    }

    /**
     * Get site key for frontend widget.
     */
    public function getSiteKey(): string {
        // Check settings DB first, fall back to env
        $key = get_setting('captcha_site_key', 'captcha', '');
        if (empty($key)) {
            $key = env('CAPTCHA_SITE_KEY', '');
        }
        return $key;
    }

    /**
     * Get secret key for server-side verification.
     */
    private function getSecretKey(): string {
        $key = get_setting('captcha_secret_key', 'captcha', '');
        if (empty($key)) {
            $key = env('CAPTCHA_SECRET_KEY', '');
        }
        return $key;
    }

    /**
     * Get CAPTCHA provider.
     */
    public function getProvider(): string {
        return get_setting('captcha_provider', 'captcha', 'turnstile');
    }

    /**
     * Verify CAPTCHA response server-side.
     */
    public function verify(?string $token): bool {
        if (!$this->isEnabled()) {
            return true; // CAPTCHA disabled, pass through
        }

        if (empty($token)) {
            return false;
        }

        $secretKey = $this->getSecretKey();
        if (empty($secretKey)) {
            // No secret key configured — log warning but allow (in development)
            if (APP_ENV === 'development') {
                return true;
            }
            error_log('CAPTCHA secret key not configured');
            return false;
        }

        $provider = $this->getProvider();
        
        switch ($provider) {
            case 'turnstile':
                return $this->verifyTurnstile($token, $secretKey);
            case 'recaptcha':
                return $this->verifyRecaptcha($token, $secretKey);
            default:
                return $this->verifyTurnstile($token, $secretKey);
        }
    }

    /**
     * Verify Cloudflare Turnstile token.
     */
    private function verifyTurnstile(string $token, string $secretKey): bool {
        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        
        $data = [
            'secret'   => $secretKey,
            'response' => $token,
            'remoteip' => get_client_ip(),
        ];

        $response = $this->httpPost($url, $data);
        
        if ($response === null) {
            error_log('Turnstile verification request failed');
            return false;
        }

        $result = json_decode($response, true);
        return isset($result['success']) && $result['success'] === true;
    }

    /**
     * Verify Google reCAPTCHA v3 token.
     */
    private function verifyRecaptcha(string $token, string $secretKey): bool {
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        
        $data = [
            'secret'   => $secretKey,
            'response' => $token,
            'remoteip' => get_client_ip(),
        ];

        $response = $this->httpPost($url, $data);
        
        if ($response === null) {
            error_log('reCAPTCHA verification request failed');
            return false;
        }

        $result = json_decode($response, true);
        return isset($result['success']) && $result['success'] === true;
    }

    /**
     * HTTP POST request helper.
     */
    private function httpPost(string $url, array $data): ?string {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("CAPTCHA HTTP request failed: $error");
            return null;
        }

        return $response;
    }
}
