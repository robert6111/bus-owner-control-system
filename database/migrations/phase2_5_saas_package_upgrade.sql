-- Phase 2.5.1 SaaS missing tables repair
-- IMPORTANT: In phpMyAdmin, first click/select the database used by app/config.php, usually bus_owner_control.
-- This repair intentionally does NOT include USE bus_owner_control, so it runs on the selected database.

CREATE TABLE IF NOT EXISTS company_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS package_tiers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  description TEXT NULL,
  setup_fee DECIMAL(14,2) NOT NULL DEFAULT 0,
  optional_monthly_fee DECIMAL(14,2) NOT NULL DEFAULT 0,
  max_users INT NOT NULL DEFAULT 0,
  max_branches INT NOT NULL DEFAULT 0,
  max_buses INT NOT NULL DEFAULT 0,
  max_routes INT NOT NULL DEFAULT 0,
  max_monthly_trips INT NOT NULL DEFAULT 0,
  features_json LONGTEXT NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  display_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS subscription_license (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_name VARCHAR(180) NOT NULL DEFAULT 'Bus Company',
  license_key VARCHAR(120) NOT NULL DEFAULT 'DEMO-LICENSE',
  plan VARCHAR(80) NOT NULL DEFAULT 'Professional Multi-Branch',
  package_code VARCHAR(40) NOT NULL DEFAULT 'professional',
  billing_model ENUM('one_time_setup','one_time_plus_optional_support','recurring_subscription') NOT NULL DEFAULT 'one_time_plus_optional_support',
  status ENUM('trial','active','expired','suspended') NOT NULL DEFAULT 'trial',
  start_date DATE NULL,
  expiry_date DATE NULL,
  setup_fee_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  setup_fee_paid ENUM('no','partial','yes') NOT NULL DEFAULT 'no',
  optional_monthly_fee DECIMAL(14,2) NOT NULL DEFAULT 0,
  optional_monthly_enabled TINYINT(1) NOT NULL DEFAULT 0,
  support_status ENUM('off','active','paused','expired') NOT NULL DEFAULT 'off',
  support_start_date DATE NULL,
  support_expiry_date DATE NULL,
  max_users INT NOT NULL DEFAULT 40,
  max_branches INT NOT NULL DEFAULT 20,
  max_buses INT NOT NULL DEFAULT 50,
  max_routes INT NOT NULL DEFAULT 80,
  max_monthly_trips INT NOT NULL DEFAULT 2000,
  notes TEXT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_subscription_package (package_code),
  INDEX idx_subscription_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  role_snapshot VARCHAR(40) NULL,
  action VARCHAR(40) NOT NULL,
  table_name VARCHAR(80) NOT NULL,
  record_id INT NULL,
  description TEXT NULL,
  old_values LONGTEXT NULL,
  new_values LONGTEXT NULL,
  ip_address VARCHAR(80) NULL,
  request_method VARCHAR(12) NULL,
  request_uri VARCHAR(500) NULL,
  user_agent VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_created (created_at),
  INDEX idx_audit_action (action),
  INDEX idx_audit_table_record (table_name, record_id),
  INDEX idx_audit_user (user_id),
  INDEX idx_audit_ip (ip_address)
) ENGINE=InnoDB;

-- Upgrade old Phase 2 subscription_license table if it already existed with fewer columns.
ALTER TABLE subscription_license
  ADD COLUMN IF NOT EXISTS package_code VARCHAR(40) NOT NULL DEFAULT 'professional' AFTER plan,
  ADD COLUMN IF NOT EXISTS billing_model ENUM('one_time_setup','one_time_plus_optional_support','recurring_subscription') NOT NULL DEFAULT 'one_time_plus_optional_support' AFTER package_code,
  ADD COLUMN IF NOT EXISTS setup_fee_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER expiry_date,
  ADD COLUMN IF NOT EXISTS setup_fee_paid ENUM('no','partial','yes') NOT NULL DEFAULT 'no' AFTER setup_fee_amount,
  ADD COLUMN IF NOT EXISTS optional_monthly_fee DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER setup_fee_paid,
  ADD COLUMN IF NOT EXISTS optional_monthly_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER optional_monthly_fee,
  ADD COLUMN IF NOT EXISTS support_status ENUM('off','active','paused','expired') NOT NULL DEFAULT 'off' AFTER optional_monthly_enabled,
  ADD COLUMN IF NOT EXISTS support_start_date DATE NULL AFTER support_status,
  ADD COLUMN IF NOT EXISTS support_expiry_date DATE NULL AFTER support_start_date,
  ADD COLUMN IF NOT EXISTS max_buses INT NOT NULL DEFAULT 50 AFTER max_branches,
  ADD COLUMN IF NOT EXISTS max_routes INT NOT NULL DEFAULT 80 AFTER max_buses,
  ADD COLUMN IF NOT EXISTS max_monthly_trips INT NOT NULL DEFAULT 2000 AFTER max_routes;

-- Upgrade old audit_logs table if it already existed with fewer columns.
ALTER TABLE audit_logs
  ADD COLUMN IF NOT EXISTS role_snapshot VARCHAR(40) NULL AFTER user_id,
  ADD COLUMN IF NOT EXISTS old_values LONGTEXT NULL AFTER description,
  ADD COLUMN IF NOT EXISTS new_values LONGTEXT NULL AFTER old_values,
  ADD COLUMN IF NOT EXISTS ip_address VARCHAR(80) NULL AFTER new_values,
  ADD COLUMN IF NOT EXISTS request_method VARCHAR(12) NULL AFTER ip_address,
  ADD COLUMN IF NOT EXISTS request_uri VARCHAR(500) NULL AFTER request_method,
  ADD COLUMN IF NOT EXISTS user_agent VARCHAR(500) NULL AFTER request_uri;

INSERT INTO package_tiers (code,name,description,setup_fee,optional_monthly_fee,max_users,max_branches,max_buses,max_routes,max_monthly_trips,features_json,status,display_order) VALUES
('starter','Starter Local','For one small office that mainly needs trips, passengers, basic reports, and strict records.',1500000,150000,5,1,5,10,120,'["dashboard","buses","routes","drivers","agents","trips","bookings","reports","users","audit_logs","settings","branding"]','active',1),
('business','Business Cloud','For growing bus companies with several branches, parcels, cash closing, and operational controls.',2500000,300000,15,5,15,25,500,'["dashboard","buses","routes","drivers","agents","trips","bookings","parcels","reports","cash_closing","reconciliation","maintenance","fuel","notifications","exports","users","audit_logs","settings","branding"]','active',2),
('professional','Professional Multi-Branch','For serious operators that need all Phase 2 controls, exports, backup, branding, and higher limits.',5000000,600000,40,20,50,80,2000,'["dashboard","buses","routes","drivers","agents","trips","bookings","parcels","reports","cash_closing","reconciliation","maintenance","fuel","notifications","exports","backup","users","audit_logs","settings","branding"]','active',3),
('enterprise','Enterprise Custom','For large operators that need high limits, custom support, integrations, and advanced deployment.',12000000,1000000,0,0,0,0,0,'["dashboard","buses","routes","drivers","agents","trips","bookings","parcels","reports","cash_closing","reconciliation","maintenance","fuel","notifications","exports","backup","users","audit_logs","settings","branding","api","custom_integrations","multi_company"]','active',4)
ON DUPLICATE KEY UPDATE
  name=VALUES(name), description=VALUES(description), setup_fee=VALUES(setup_fee), optional_monthly_fee=VALUES(optional_monthly_fee),
  max_users=VALUES(max_users), max_branches=VALUES(max_branches), max_buses=VALUES(max_buses), max_routes=VALUES(max_routes),
  max_monthly_trips=VALUES(max_monthly_trips), features_json=VALUES(features_json), status=VALUES(status), display_order=VALUES(display_order);

INSERT INTO company_settings (setting_key, setting_value) VALUES
('company_name', 'Bus Owner Control Company'),
('company_phone', '+255 700 000 000'),
('company_email', 'info@example.com'),
('company_address', 'Dar es Salaam, Tanzania'),
('receipt_footer', 'Thank you for travelling with us.'),
('whatsapp_country_code', '255'),
('backup_retention_days', '30')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO subscription_license (company_name, license_key, plan, package_code, billing_model, status, start_date, expiry_date, setup_fee_amount, setup_fee_paid, optional_monthly_fee, optional_monthly_enabled, support_status, support_start_date, support_expiry_date, max_users, max_branches, max_buses, max_routes, max_monthly_trips, notes)
SELECT 'Bus Owner Control Company', 'DEMO-SAAS-PRO-LOCAL', 'Professional Multi-Branch', 'professional', 'one_time_plus_optional_support', 'trial', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), 5000000, 'partial', 600000, 1, 'active', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 40, 20, 50, 80, 2000, 'Local demo licence for testing the package/licence-ready SaaS model.'
WHERE NOT EXISTS (SELECT 1 FROM subscription_license LIMIT 1);

UPDATE subscription_license sl
LEFT JOIN package_tiers pt ON pt.code = COALESCE(NULLIF(sl.package_code,''), 'professional')
SET
  sl.package_code = COALESCE(NULLIF(sl.package_code,''), 'professional'),
  sl.plan = COALESCE(pt.name, sl.plan),
  sl.billing_model = COALESCE(NULLIF(sl.billing_model,''), 'one_time_plus_optional_support'),
  sl.setup_fee_amount = IF(sl.setup_fee_amount = 0, COALESCE(pt.setup_fee, 5000000), sl.setup_fee_amount),
  sl.optional_monthly_fee = IF(sl.optional_monthly_fee = 0, COALESCE(pt.optional_monthly_fee, 600000), sl.optional_monthly_fee),
  sl.max_users = IF(sl.max_users = 0, COALESCE(pt.max_users, 40), sl.max_users),
  sl.max_branches = IF(sl.max_branches = 0, COALESCE(pt.max_branches, 20), sl.max_branches),
  sl.max_buses = IF(sl.max_buses = 0, COALESCE(pt.max_buses, 50), sl.max_buses),
  sl.max_routes = IF(sl.max_routes = 0, COALESCE(pt.max_routes, 80), sl.max_routes),
  sl.max_monthly_trips = IF(sl.max_monthly_trips = 0, COALESCE(pt.max_monthly_trips, 2000), sl.max_monthly_trips)
WHERE sl.id IS NOT NULL;
