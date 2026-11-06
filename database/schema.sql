CREATE TABLE IF NOT EXISTS clients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('ADMIN','ANALYST','VIEWER') NOT NULL DEFAULT 'VIEWER',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS provider_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(32) NOT NULL,
  external_account_id VARCHAR(160) NOT NULL,
  display_name VARCHAR(160) NULL,
  credentials_ref VARCHAR(255) NULL,
  config_json JSON NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_provider_account (client_id, provider, external_account_id),
  CONSTRAINT fk_provider_accounts_client FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS daily_insights (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id BIGINT UNSIGNED NOT NULL,
  provider_account_id BIGINT UNSIGNED NOT NULL,
  entity_type ENUM('account','campaign','adset','ad') NOT NULL,
  entity_id VARCHAR(160) NOT NULL,
  entity_name VARCHAR(255) NULL,
  insight_date DATE NOT NULL,
  spend DECIMAL(18,6) NOT NULL DEFAULT 0,
  impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
  clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
  conversions DECIMAL(18,6) NOT NULL DEFAULT 0,
  revenue DECIMAL(18,6) NOT NULL DEFAULT 0,
  frequency DECIMAL(18,6) NULL,
  ctr DECIMAL(18,6) NULL,
  raw_payload JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_daily_insight (provider_account_id, entity_type, entity_id, insight_date),
  KEY idx_client_date (client_id, insight_date),
  CONSTRAINT fk_insights_client FOREIGN KEY (client_id) REFERENCES clients(id),
  CONSTRAINT fk_insights_account FOREIGN KEY (provider_account_id) REFERENCES provider_accounts(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id BIGINT UNSIGNED NOT NULL,
  target_cpa DECIMAL(18,6) NULL,
  fatigue_frequency_warning DECIMAL(18,6) NOT NULL DEFAULT 2.5,
  max_daily_budget DECIMAL(18,6) NULL,
  max_budget_change_percent DECIMAL(8,3) NOT NULL DEFAULT 15,
  min_spend_before_action DECIMAL(18,6) NOT NULL DEFAULT 100,
  auto_actions_enabled TINYINT(1) NOT NULL DEFAULT 0,
  kill_switch TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rules_client (client_id),
  CONSTRAINT fk_rules_client FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recommendations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id BIGINT UNSIGNED NOT NULL,
  kind VARCHAR(64) NOT NULL,
  risk_level ENUM('LOW','MEDIUM','HIGH') NOT NULL DEFAULT 'MEDIUM',
  title VARCHAR(255) NOT NULL,
  evidence_json JSON NOT NULL,
  status ENUM('DRAFT','PENDING','APPROVED','REJECTED','EXECUTED','EXPIRED') NOT NULL DEFAULT 'DRAFT',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  CONSTRAINT fk_recommendations_client FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS approval_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recommendation_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  status ENUM('PENDING','APPROVED','REJECTED','EXPIRED') NOT NULL DEFAULT 'PENDING',
  approved_by VARCHAR(160) NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_approval_token (token_hash),
  CONSTRAINT fk_approval_recommendation FOREIGN KEY (recommendation_id) REFERENCES recommendations(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id BIGINT UNSIGNED NULL,
  actor_type VARCHAR(32) NOT NULL,
  actor_id VARCHAR(160) NULL,
  action VARCHAR(120) NOT NULL,
  target_type VARCHAR(64) NULL,
  target_id VARCHAR(160) NULL,
  before_json JSON NULL,
  after_json JSON NULL,
  reason TEXT NULL,
  correlation_id CHAR(36) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_client_time (client_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS api_request_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(64) NOT NULL,
  endpoint VARCHAR(255) NOT NULL,
  method VARCHAR(10) NOT NULL,
  status_code SMALLINT NULL,
  duration_ms INT UNSIGNED NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  error_message TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS job_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_name VARCHAR(120) NOT NULL,
  client_id BIGINT UNSIGNED NULL,
  status ENUM('RUNNING','SUCCESS','FAILED') NOT NULL,
  records_processed INT UNSIGNED NOT NULL DEFAULT 0,
  error_message TEXT NULL,
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at TIMESTAMP NULL
) ENGINE=InnoDB;
