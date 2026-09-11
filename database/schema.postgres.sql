CREATE TABLE IF NOT EXISTS admins (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  email VARCHAR(255), password_hash VARCHAR(255) NOT NULL,
  force_password_change BOOLEAN NOT NULL DEFAULT TRUE,
  login_attempts INTEGER NOT NULL DEFAULT 0, locked_until TIMESTAMPTZ,
  last_login_at TIMESTAMPTZ, last_login_ip INET, created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS tasks (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  title VARCHAR(255) NOT NULL, description TEXT, type VARCHAR(50) NOT NULL DEFAULT 'custom', url VARCHAR(512),
  required BOOLEAN NOT NULL DEFAULT TRUE, enabled BOOLEAN NOT NULL DEFAULT TRUE, sort_order INTEGER NOT NULL DEFAULT 0,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS applications (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  application_id VARCHAR(20) UNIQUE NOT NULL, wallet_address VARCHAR(255) NOT NULL, wallet_normalized VARCHAR(255) UNIQUE NOT NULL,
  twitter_username VARCHAR(100), email VARCHAR(255),
  ip_address INET NOT NULL, user_agent TEXT, status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected','blacklisted','review')),
  admin_notes TEXT, created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS application_tasks (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY, application_id BIGINT NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
  task_id BIGINT NOT NULL REFERENCES tasks(id) ON DELETE CASCADE, completed BOOLEAN NOT NULL DEFAULT TRUE, proof VARCHAR(512), created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), UNIQUE(application_id, task_id)
);
CREATE TABLE IF NOT EXISTS blacklist (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY, type VARCHAR(20) NOT NULL CHECK (type IN ('wallet','ip','twitter')), value VARCHAR(255) NOT NULL, reason TEXT, created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), UNIQUE(type, value)
);
CREATE TABLE IF NOT EXISTS blocked_ips (id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY, ip_address INET UNIQUE NOT NULL, reason TEXT, blocked_at TIMESTAMPTZ NOT NULL DEFAULT NOW());
CREATE TABLE IF NOT EXISTS rate_limits (id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY, ip_address INET NOT NULL, endpoint VARCHAR(100) NOT NULL DEFAULT 'apply', created_at TIMESTAMPTZ NOT NULL DEFAULT NOW());
CREATE TABLE IF NOT EXISTS settings (id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY, setting_group VARCHAR(50) NOT NULL DEFAULT 'general', setting_key VARCHAR(100) NOT NULL, setting_value TEXT, created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), UNIQUE(setting_group, setting_key));
CREATE TABLE IF NOT EXISTS admin_logs (id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY, admin_id BIGINT REFERENCES admins(id) ON DELETE SET NULL, admin_username VARCHAR(50), action VARCHAR(100) NOT NULL, target_type VARCHAR(50), target_id VARCHAR(100), details TEXT, ip_address INET, created_at TIMESTAMPTZ NOT NULL DEFAULT NOW());
CREATE INDEX IF NOT EXISTS idx_applications_ip_created ON applications(ip_address, created_at);
CREATE INDEX IF NOT EXISTS idx_applications_status_created ON applications(status, created_at);
CREATE INDEX IF NOT EXISTS idx_rate_limits_endpoint_created ON rate_limits(ip_address, endpoint, created_at);
