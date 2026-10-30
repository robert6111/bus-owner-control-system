USE bus_owner_control;

CREATE TABLE IF NOT EXISTS company_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO company_settings (setting_key, setting_value) VALUES
('company_name', 'Bus Owner Control Company'),
('company_phone', '+255 700 000 000'),
('company_email', 'info@example.com'),
('company_address', 'Dar es Salaam, Tanzania'),
('receipt_footer', 'Thank you for travelling with us.'),
('whatsapp_country_code', '255'),
('backup_retention_days', '30')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

CREATE TABLE IF NOT EXISTS subscription_license (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_name VARCHAR(180) NOT NULL DEFAULT 'Bus Company',
  license_key VARCHAR(120) NOT NULL DEFAULT 'DEMO-LICENSE',
  plan VARCHAR(80) NOT NULL DEFAULT 'Phase 2 Demo',
  status ENUM('trial','active','expired','suspended') NOT NULL DEFAULT 'trial',
  start_date DATE NULL,
  expiry_date DATE NULL,
  max_users INT NOT NULL DEFAULT 10,
  max_branches INT NOT NULL DEFAULT 5,
  notes TEXT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO subscription_license (company_name, license_key, plan, status, start_date, expiry_date, max_users, max_branches, notes)
SELECT 'Bus Owner Control Company', 'DEMO-PHASE2-LOCAL', 'Phase 2 Demo', 'trial', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), 50, 20, 'Local demo licence for testing Phase 2 features.'
WHERE NOT EXISTS (SELECT 1 FROM subscription_license LIMIT 1);

CREATE TABLE IF NOT EXISTS cash_closings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  closing_date DATE NOT NULL,
  agent_id INT NULL,
  ticket_cash_expected DECIMAL(12,2) NOT NULL DEFAULT 0,
  parcel_cash_expected DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_cash_expected DECIMAL(12,2) NOT NULL DEFAULT 0,
  cash_submitted DECIMAL(12,2) NOT NULL DEFAULT 0,
  mobile_money_submitted DECIMAL(12,2) NOT NULL DEFAULT 0,
  bank_submitted DECIMAL(12,2) NOT NULL DEFAULT 0,
  expenses_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
  variance DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('draft','submitted','approved') NOT NULL DEFAULT 'submitted',
  prepared_by INT NULL,
  approved_by INT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_cash_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE SET NULL,
  CONSTRAINT fk_cash_prepared_by FOREIGN KEY (prepared_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_cash_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_cash_date_agent (closing_date, agent_id),
  INDEX idx_cash_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS maintenance_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bus_id INT NOT NULL,
  maintenance_type ENUM('service','repair','inspection','tyres','oil','other') NOT NULL DEFAULT 'service',
  service_date DATE NOT NULL,
  odometer_km INT NULL,
  vendor VARCHAR(160) NULL,
  description TEXT NOT NULL,
  cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('planned','in_progress','completed','cancelled') NOT NULL DEFAULT 'planned',
  next_due_date DATE NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_maintenance_bus FOREIGN KEY (bus_id) REFERENCES buses(id),
  CONSTRAINT fk_maintenance_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_maintenance_bus_date (bus_id, service_date),
  INDEX idx_maintenance_due (next_due_date, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fuel_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bus_id INT NOT NULL,
  trip_id INT NULL,
  fuel_date DATE NOT NULL,
  litres DECIMAL(10,2) NOT NULL DEFAULT 0,
  price_per_litre DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  odometer_km INT NULL,
  station VARCHAR(160) NULL,
  notes TEXT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_fuel_bus FOREIGN KEY (bus_id) REFERENCES buses(id),
  CONSTRAINT fk_fuel_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL,
  CONSTRAINT fk_fuel_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_fuel_date_bus (fuel_date, bus_id),
  INDEX idx_fuel_trip (trip_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notification_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  channel ENUM('whatsapp','sms','email','manual') NOT NULL DEFAULT 'whatsapp',
  recipient_name VARCHAR(160) NULL,
  recipient_phone VARCHAR(60) NULL,
  message TEXT NOT NULL,
  related_table VARCHAR(80) NULL,
  record_id INT NULL,
  status ENUM('prepared','opened','sent','failed') NOT NULL DEFAULT 'prepared',
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notification_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_notification_related (related_table, record_id),
  INDEX idx_notification_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS backup_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  file_name VARCHAR(255) NOT NULL,
  file_size BIGINT NOT NULL DEFAULT 0,
  status ENUM('created','failed') NOT NULL DEFAULT 'created',
  notes TEXT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_backup_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_backup_created (created_at)
) ENGINE=InnoDB;
