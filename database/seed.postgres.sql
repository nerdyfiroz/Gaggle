INSERT INTO settings(setting_group,setting_key,setting_value) VALUES
('general','project_name','Gaggle'),('general','project_tagline','The GAGGLE is coming'),('general','blockchain','Robinhood'),('general','wl_spots','100'),('general','twitter_url','https://x.com/itzGaggle'),
('application','applications_enabled','1'),('application','ip_application_limit','100'),('application','rate_limit_window_minutes','10'),('application','rate_limit_max_requests','5'),('application','duplicate_wallet_protection','1'),('application','require_twitter','1'),('application','require_email','0'),('captcha','captcha_enabled','1'),('security','ip_blocking_enabled','1')
ON CONFLICT(setting_group,setting_key) DO NOTHING;

INSERT INTO tasks(title,description,type,url,required,enabled,sort_order)
SELECT 'Follow @itzGaggle on X','Follow the official Gaggle account.','twitter_follow','https://x.com/itzGaggle',true,true,1
WHERE NOT EXISTS (SELECT 1 FROM tasks WHERE title='Follow @itzGaggle on X');
INSERT INTO tasks(title,description,type,url,required,enabled,sort_order)
SELECT 'Like the Gaggle post','Like the post linked by the admin.','twitter_like',NULL,false,false,2
WHERE NOT EXISTS (SELECT 1 FROM tasks WHERE title='Like the Gaggle post');
INSERT INTO tasks(title,description,type,url,required,enabled,sort_order)
SELECT 'Retweet the Gaggle post','Retweet the post linked by the admin.','twitter_retweet',NULL,false,false,3
WHERE NOT EXISTS (SELECT 1 FROM tasks WHERE title='Retweet the Gaggle post');
INSERT INTO tasks(title,description,type,url,required,enabled,sort_order)
SELECT 'Reply to the Gaggle post','Reply to the post linked by the admin.','twitter_reply',NULL,false,false,4
WHERE NOT EXISTS (SELECT 1 FROM tasks WHERE title='Reply to the Gaggle post');
