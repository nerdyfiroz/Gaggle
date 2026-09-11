-- ===========================================
-- Gaggle NFT Collection — Seed Data
-- ===========================================
-- Run AFTER schema.sql
-- Default admin password: ChangeMe123!
-- Admin MUST change password on first login.

SET NAMES utf8mb4;

-- -------------------------------------------
-- Default Admin (password: ChangeMe123!)
-- -------------------------------------------
INSERT INTO `admins` (`username`, `email`, `password_hash`, `force_password_change`) VALUES
('admin', 'admin@gaggle.io', '$2y$12$LJ3m4yO5TpCn4oVKfVfLkuQGvJbFxLqKp7oKvE8eR0sDtIhJqXbXK', 1);

-- -------------------------------------------
-- Default Settings
-- -------------------------------------------
-- General
INSERT INTO `settings` (`setting_group`, `setting_key`, `setting_value`) VALUES
('general', 'project_name', 'Gaggle'),
('general', 'project_description', 'The flock is taking over the blockchain. 5,555 unique pixel-art ducks ready to waddle into your wallet.'),
('general', 'project_tagline', 'The flock is coming.'),
('general', 'twitter_url', 'https://x.com/itzGaggle'),
('general', 'discord_url', 'https://discord.gg/gaggle'),
('general', 'telegram_url', 'https://t.me/gaggle'),
('general', 'website_url', 'https://gaggle.io'),
('general', 'mint_date', 'TBA'),
('general', 'supply', '5,555'),
('general', 'mint_price', '0.05 ETH'),
('general', 'blockchain', 'Ethereum'),
('general', 'wl_spots', '2,000');

-- Application settings
INSERT INTO `settings` (`setting_group`, `setting_key`, `setting_value`) VALUES
('application', 'applications_enabled', '1'),
('application', 'ip_application_limit', '100'),
('application', 'rate_limit_window_minutes', '10'),
('application', 'rate_limit_max_requests', '5'),
('application', 'duplicate_wallet_protection', '1'),
('application', 'duplicate_twitter_protection', '0'),
('application', 'duplicate_discord_protection', '0'),
('application', 'require_email', '0'),
('application', 'require_discord', '1'),
('application', 'require_twitter', '1'),
('application', 'require_telegram', '0'),
('application', 'show_email_field', '1'),
('application', 'show_telegram_field', '1');

-- CAPTCHA settings
INSERT INTO `settings` (`setting_group`, `setting_key`, `setting_value`) VALUES
('captcha', 'captcha_enabled', '1'),
('captcha', 'captcha_provider', 'turnstile'),
('captcha', 'captcha_site_key', ''),
('captcha', 'captcha_secret_key', '');

-- Security settings
INSERT INTO `settings` (`setting_group`, `setting_key`, `setting_value`) VALUES
('security', 'session_timeout', '3600'),
('security', 'csrf_enabled', '1'),
('security', 'ip_blocking_enabled', '1'),
('security', 'honeypot_enabled', '1');

-- -------------------------------------------
-- Default Tasks
-- -------------------------------------------
INSERT INTO `tasks` (`title`, `description`, `type`, `url`, `required`, `enabled`, `sort_order`) VALUES
('Follow @itzGaggle on X', 'Follow the official Gaggle account on X (Twitter) to stay updated on mint announcements and community events.', 'twitter_follow', 'https://x.com/itzGaggle', 1, 1, 1),
('Join Gaggle Discord', 'Join our Discord server to connect with the community, participate in events, and get early access to announcements.', 'discord_join', 'https://discord.gg/gaggle', 1, 1, 2),
('Like & Repost Announcement', 'Like and repost our latest announcement post on X to help spread the word about Gaggle.', 'twitter_repost', 'https://x.com/itzGaggle/status/2098329986637639987', 1, 1, 3),
('Join Telegram', 'Join the Gaggle Telegram channel for real-time updates and community chat.', 'telegram_join', 'https://t.me/gaggle', 0, 1, 4),
('Submit Wallet Address', 'Provide your Ethereum wallet address where you want to receive your Gaggle NFT.', 'custom', '', 1, 1, 5);
