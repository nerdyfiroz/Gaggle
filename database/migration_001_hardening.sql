-- Gaggle hardening migration for installations created from the original schema.
-- Review duplicate wallet rows before adding the unique key in a live database.
SET NAMES utf8mb4;

ALTER TABLE applications
    ADD COLUMN wallet_normalized VARCHAR(255) NULL AFTER wallet_address;

UPDATE applications
SET wallet_normalized = LOWER(TRIM(wallet_address))
WHERE wallet_normalized IS NULL;

ALTER TABLE applications
    MODIFY wallet_normalized VARCHAR(255) NOT NULL,
    ADD UNIQUE KEY uq_wallet_normalized (wallet_normalized);

ALTER TABLE application_tasks
    ADD UNIQUE KEY uq_application_task (application_id, task_id);

INSERT INTO settings (setting_group, setting_key, setting_value)
VALUES ('application', 'duplicate_twitter_protection', '0'),
       ('application', 'duplicate_discord_protection', '0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
