<?php
/**
 * Gaggle NFT — Validation Service
 * 
 * Server-side validation for whitelist applications.
 */

class ValidationService {

    /**
     * Validate a whitelist application submission.
     * Returns array of errors (empty if valid).
     */
    public function validateApplication(array $data): array {
        $errors = [];

        // ---- Wallet Address (always required) ----
        if (empty($data['wallet_address'])) {
            $errors['wallet_address'] = 'Wallet address is required.';
        } else {
            $walletError = $this->validateWallet($data['wallet_address']);
            if ($walletError) {
                $errors['wallet_address'] = $walletError;
            }
        }

        // ---- Twitter/X ----
        $requireTwitter = get_setting('require_twitter', 'application', '1') === '1';
        if ($requireTwitter) {
            if (empty($data['twitter_username'])) {
                $errors['twitter_username'] = 'Twitter/X username is required.';
            } elseif (!$this->isValidTwitter($data['twitter_username'])) {
                $errors['twitter_username'] = 'Please enter a valid Twitter/X username.';
            }
        } elseif (!empty($data['twitter_username']) && !$this->isValidTwitter($data['twitter_username'])) {
            $errors['twitter_username'] = 'Please enter a valid Twitter/X username.';
        }

        // ---- Discord ----
        $requireDiscord = get_setting('require_discord', 'application', '1') === '1';
        if ($requireDiscord) {
            if (empty($data['discord_username'])) {
                $errors['discord_username'] = 'Discord username is required.';
            } elseif (!$this->isValidDiscord($data['discord_username'])) {
                $errors['discord_username'] = 'Please enter a valid Discord username.';
            }
        } elseif (!empty($data['discord_username']) && !$this->isValidDiscord($data['discord_username'])) {
            $errors['discord_username'] = 'Please enter a valid Discord username.';
        }

        // ---- Email ----
        $requireEmail = get_setting('require_email', 'application', '0') === '1';
        if ($requireEmail) {
            if (empty($data['email'])) {
                $errors['email'] = 'Email address is required.';
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Please enter a valid email address.';
            }
        } elseif (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        // ---- Telegram ----
        $requireTelegram = get_setting('require_telegram', 'application', '0') === '1';
        if ($requireTelegram) {
            if (empty($data['telegram_username'])) {
                $errors['telegram_username'] = 'Telegram username is required.';
            } elseif (!$this->isValidTelegram($data['telegram_username'])) {
                $errors['telegram_username'] = 'Please enter a valid Telegram username.';
            }
        } elseif (!empty($data['telegram_username']) && !$this->isValidTelegram($data['telegram_username'])) {
            $errors['telegram_username'] = 'Please enter a valid Telegram username.';
        }

        // ---- Honeypot ----
        $honeypotEnabled = get_setting('honeypot_enabled', 'security', '1') === '1';
        if ($honeypotEnabled && !empty($data['website_url'])) {
            // Honeypot field was filled — bot detected
            $errors['bot'] = 'Submission rejected.';
        }

        return $errors;
    }

    /**
     * Validate wallet address.
     * Supports Ethereum by default, extensible for other chains.
     */
    public function validateWallet(string $wallet): ?string {
        $wallet = trim($wallet);
        $blockchain = strtolower(get_setting('blockchain', 'general', 'ethereum'));

        switch ($blockchain) {
            case 'ethereum':
            case 'eth':
            case 'polygon':
            case 'matic':
            case 'arbitrum':
            case 'optimism':
            case 'base':
            case 'avalanche':
            case 'bsc':
            case 'bnb chain':
                return $this->validateEthereumAddress($wallet);

            case 'solana':
            case 'sol':
                return $this->validateSolanaAddress($wallet);

            case 'bitcoin':
            case 'btc':
                return $this->validateBitcoinAddress($wallet);

            default:
                return $this->validateEthereumAddress($wallet);
        }
    }

    /**
     * Validate Ethereum address (0x followed by 40 hex characters).
     */
    private function validateEthereumAddress(string $address): ?string {
        if (!preg_match('/^0x[0-9a-fA-F]{40}$/', $address)) {
            return 'Please enter a valid Ethereum wallet address (0x...).';
        }
        return null;
    }

    /**
     * Validate Solana address (32-44 base58 characters).
     */
    private function validateSolanaAddress(string $address): ?string {
        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $address)) {
            return 'Please enter a valid Solana wallet address.';
        }
        return null;
    }

    /**
     * Validate Bitcoin address (basic format check).
     */
    private function validateBitcoinAddress(string $address): ?string {
        // Legacy (1...), SegWit (3...), Bech32 (bc1...)
        if (!preg_match('/^(1|3)[1-9A-HJ-NP-Za-km-z]{25,34}$/', $address) &&
            !preg_match('/^bc1[a-zA-HJ-NP-Z0-9]{25,87}$/', $address)) {
            return 'Please enter a valid Bitcoin wallet address.';
        }
        return null;
    }

    /**
     * Validate Twitter/X username.
     */
    private function isValidTwitter(string $username): bool {
        // Remove @ prefix if present
        $username = ltrim($username, '@');
        return (bool) preg_match('/^[A-Za-z0-9_]{1,15}$/', $username);
    }

    /**
     * Validate Discord username (new format: lowercase, 2-32 chars).
     */
    private function isValidDiscord(string $username): bool {
        // Support both old format (User#1234) and new format (username)
        if (preg_match('/^.{2,32}#\d{4}$/', $username)) return true;
        if (preg_match('/^[a-z0-9_.]{2,32}$/', strtolower($username))) return true;
        // Also allow display names
        return strlen($username) >= 2 && strlen($username) <= 32;
    }

    /**
     * Validate Telegram username.
     */
    private function isValidTelegram(string $username): bool {
        $username = ltrim($username, '@');
        return (bool) preg_match('/^[A-Za-z0-9_]{5,32}$/', $username);
    }

    /**
     * Sanitize Twitter username (normalize).
     */
    public function sanitizeTwitter(string $username): string {
        return strtolower(ltrim(trim($username), '@'));
    }

    /**
     * Sanitize Discord username (normalize).
     */
    public function sanitizeDiscord(string $username): string {
        return trim($username);
    }

    /**
     * Sanitize Telegram username (normalize).
     */
    public function sanitizeTelegram(string $username): string {
        return strtolower(ltrim(trim($username), '@'));
    }

    /**
     * Sanitize wallet address.
     */
    public function sanitizeWallet(string $wallet): string {
        return trim($wallet);
    }
}
