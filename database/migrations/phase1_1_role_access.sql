USE bus_owner_control;

-- Run this only if you already imported the older Phase 1 database.
-- New installations should import database/schema.sql instead.

ALTER TABLE users
  MODIFY role ENUM('owner','admin','manager','accountant','agent','parcel_officer') NOT NULL DEFAULT 'manager';

ALTER TABLE users
  ADD COLUMN agent_id INT NULL AFTER role,
  ADD COLUMN last_login_at DATETIME NULL AFTER status,
  ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE users
  ADD INDEX idx_users_role_status (role, status),
  ADD INDEX idx_users_agent (agent_id),
  ADD CONSTRAINT fk_users_agent FOREIGN KEY (agent_id) REFERENCES agents(id);

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(40) NOT NULL,
  table_name VARCHAR(80) NOT NULL,
  record_id INT NULL,
  description TEXT NULL,
  ip_address VARCHAR(80) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_created (created_at),
  INDEX idx_audit_table_record (table_name, record_id),
  INDEX idx_audit_user (user_id)
) ENGINE=InnoDB;

-- Optional demo staff accounts, all using password: Admin123!
INSERT IGNORE INTO users (name, email, password_hash, role, agent_id, status) VALUES
('Operations Manager', 'manager@buspro.local', '$2y$12$O58FSLpFIunlPR4nBXzCCuYHK7TFR5qITmXjH31pYOCoQ9zTIVCxK', 'manager', NULL, 'active'),
('Accountant', 'accountant@buspro.local', '$2y$12$O58FSLpFIunlPR4nBXzCCuYHK7TFR5qITmXjH31pYOCoQ9zTIVCxK', 'accountant', NULL, 'active'),
('Urafiki Ticket Agent', 'agent@buspro.local', '$2y$12$O58FSLpFIunlPR4nBXzCCuYHK7TFR5qITmXjH31pYOCoQ9zTIVCxK', 'agent', 1, 'active'),
('Posta Parcel Officer', 'parcel@buspro.local', '$2y$12$O58FSLpFIunlPR4nBXzCCuYHK7TFR5qITmXjH31pYOCoQ9zTIVCxK', 'parcel_officer', 2, 'active');
