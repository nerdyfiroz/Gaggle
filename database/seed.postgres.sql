INSERT INTO settings(setting_group,setting_key,setting_value) VALUES
('general','project_name','Gaggle'),('general','project_tagline','The flock is coming.'),('general','blockchain','Ethereum'),('general','wl_spots','100'),('general','twitter_url','https://x.com/itzGaggle'),
('application','applications_enabled','1'),('application','ip_application_limit','100'),('application','rate_limit_window_minutes','10'),('application','rate_limit_max_requests','5'),('application','duplicate_wallet_protection','1'),('application','require_twitter','1'),('application','require_email','0'),('captcha','captcha_enabled','1'),('captcha','captcha_provider','turnstile'),('security','ip_blocking_enabled','1')
ON CONFLICT(setting_group,setting_key) DO NOTHING;

INSERT INTO tasks(title,description,type,url,required,enabled,sort_order) VALUES
('Follow @itzGaggle on X','Follow the official Gaggle account.','twitter_follow','https://x.com/itzGaggle',true,true,1)
ON CONFLICT DO NOTHING;
