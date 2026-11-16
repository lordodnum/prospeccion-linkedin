-- Modulo LinkedIn aparte — esquema minimo (MySQL/SQLite compatible)
CREATE TABLE IF NOT EXISTS linkedin_accounts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  provider VARCHAR(20) DEFAULT 'unipile',
  unipile_account_id VARCHAR(64) UNIQUE NOT NULL,
  nombre VARCHAR(190),
  estado VARCHAR(20) DEFAULT 'connected',
  daily_invite_limit INT DEFAULT 20,
  daily_msg_limit INT DEFAULT 80,
  last_sync_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS linkedin_prospects (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  linkedin_account_id INT NOT NULL,
  linkedin_urn VARCHAR(190),
  nombre VARCHAR(190),
  cargo VARCHAR(255),
  empresa VARCHAR(255),
  linkedin_url VARCHAR(255),
  status VARCHAR(20) DEFAULT 'nuevo',
  invite_sent_at DATETIME NULL,
  last_msg_at DATETIME NULL,
  followup_step INT DEFAULT 0,
  meta TEXT NULL
);
CREATE TABLE IF NOT EXISTS linkedin_daily_quotas (
  account_id INT NOT NULL,
  fecha DATE NOT NULL,
  invites INT DEFAULT 0,
  messages INT DEFAULT 0,
  PRIMARY KEY (account_id, fecha)
);
CREATE TABLE IF NOT EXISTS linkedin_blocklist (
  linkedin_url VARCHAR(255) PRIMARY KEY,
  motivo VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
