CREATE DATABASE IF NOT EXISTS bus_owner_control CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bus_owner_control;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS notification_logs;
DROP TABLE IF EXISTS backup_logs;
DROP TABLE IF EXISTS fuel_logs;
DROP TABLE IF EXISTS maintenance_records;
DROP TABLE IF EXISTS cash_closings;
DROP TABLE IF EXISTS subscription_license;
DROP TABLE IF EXISTS package_tiers;
DROP TABLE IF EXISTS company_settings;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS parcels;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS trips;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS agents;
DROP TABLE IF EXISTS drivers;
DROP TABLE IF EXISTS routes;
DROP TABLE IF EXISTS buses;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE agents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  phone VARCHAR(60) NULL,
  branch VARCHAR(120) NOT NULL,
  commission_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner','admin','manager','accountant','agent','parcel_officer') NOT NULL DEFAULT 'manager',
  agent_id INT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_agent FOREIGN KEY (agent_id) REFERENCES agents(id),
  INDEX idx_users_role_status (role, status),
  INDEX idx_users_agent (agent_id)
) ENGINE=InnoDB;

CREATE TABLE buses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bus_number VARCHAR(80) NOT NULL,
  plate_number VARCHAR(40) NOT NULL UNIQUE,
  capacity INT NOT NULL,
  model VARCHAR(120) NULL,
  status ENUM('active','maintenance','inactive') NOT NULL DEFAULT 'active',
  latra_license_expiry DATE NULL,
  insurance_expiry DATE NULL,
  next_service_date DATE NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE routes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  origin VARCHAR(120) NOT NULL,
  destination VARCHAR(120) NOT NULL,
  distance_km DECIMAL(10,2) NULL,
  standard_fare DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_routes_origin_destination (origin, destination)
) ENGINE=InnoDB;

CREATE TABLE drivers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  phone VARCHAR(60) NULL,
  license_number VARCHAR(100) NULL,
  license_expiry DATE NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE trips (
  id INT AUTO_INCREMENT PRIMARY KEY,
  route_id INT NOT NULL,
  bus_id INT NOT NULL,
  driver_id INT NULL,
  conductor_name VARCHAR(160) NULL,
  departure_date DATE NOT NULL,
  departure_time TIME NOT NULL,
  fare DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('scheduled','departed','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_trips_route FOREIGN KEY (route_id) REFERENCES routes(id),
  CONSTRAINT fk_trips_bus FOREIGN KEY (bus_id) REFERENCES buses(id),
  CONSTRAINT fk_trips_driver FOREIGN KEY (driver_id) REFERENCES drivers(id),
  INDEX idx_trips_date (departure_date),
  INDEX idx_trips_bus_date (bus_id, departure_date)
) ENGINE=InnoDB;

CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trip_id INT NOT NULL,
  agent_id INT NULL,
  passenger_name VARCHAR(160) NOT NULL,
  phone VARCHAR(60) NULL,
  seat_no VARCHAR(20) NOT NULL,
  boarding_point VARCHAR(120) NULL,
  destination_point VARCHAR(120) NULL,
  amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_method ENUM('cash','mobile_money','bank') NOT NULL DEFAULT 'cash',
  status ENUM('booked','paid','boarded','cancelled') NOT NULL DEFAULT 'booked',
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bookings_trip FOREIGN KEY (trip_id) REFERENCES trips(id),
  CONSTRAINT fk_bookings_agent FOREIGN KEY (agent_id) REFERENCES agents(id),
  INDEX idx_bookings_trip (trip_id),
  INDEX idx_bookings_agent (agent_id)
) ENGINE=InnoDB;

CREATE TABLE parcels (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tracking_code VARCHAR(50) NOT NULL UNIQUE,
  trip_id INT NULL,
  agent_id INT NULL,
  sender_name VARCHAR(160) NOT NULL,
  sender_phone VARCHAR(60) NULL,
  receiver_name VARCHAR(160) NOT NULL,
  receiver_phone VARCHAR(60) NULL,
  description TEXT NOT NULL,
  from_branch VARCHAR(120) NULL,
  to_branch VARCHAR(120) NULL,
  amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_method ENUM('cash','mobile_money','bank') NOT NULL DEFAULT 'cash',
  status ENUM('received','loaded','in_transit','delivered','cancelled') NOT NULL DEFAULT 'received',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_parcels_trip FOREIGN KEY (trip_id) REFERENCES trips(id),
  CONSTRAINT fk_parcels_agent FOREIGN KEY (agent_id) REFERENCES agents(id),
  INDEX idx_parcels_tracking (tracking_code),
  INDEX idx_parcels_trip (trip_id),
  INDEX idx_parcels_agent (agent_id)
) ENGINE=InnoDB;

CREATE TABLE expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trip_id INT NULL,
  bus_id INT NULL,
  category ENUM('fuel','repair','tyres','allowance','office','tax_fee','other') NOT NULL DEFAULT 'other',
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  expense_date DATE NOT NULL,
  paid_by VARCHAR(160) NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_expenses_trip FOREIGN KEY (trip_id) REFERENCES trips(id),
  CONSTRAINT fk_expenses_bus FOREIGN KEY (bus_id) REFERENCES buses(id),
  INDEX idx_expenses_date (expense_date),
  INDEX idx_expenses_bus (bus_id),
  INDEX idx_expenses_trip (trip_id)
) ENGINE=InnoDB;


CREATE TABLE company_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE package_tiers (
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

CREATE TABLE subscription_license (
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

CREATE TABLE cash_closings (
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

CREATE TABLE maintenance_records (
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

CREATE TABLE fuel_logs (
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

CREATE TABLE notification_logs (
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

CREATE TABLE backup_logs (
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

CREATE TABLE audit_logs (
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
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_created (created_at),
  INDEX idx_audit_action (action),
  INDEX idx_audit_table_record (table_name, record_id),
  INDEX idx_audit_user (user_id),
  INDEX idx_audit_ip (ip_address)
) ENGINE=InnoDB;


INSERT INTO package_tiers (code,name,description,setup_fee,optional_monthly_fee,max_users,max_branches,max_buses,max_routes,max_monthly_trips,features_json,status,display_order) VALUES
('starter','Starter Local','For one small office that mainly needs trips, passengers, basic reports, and strict records.',1500000,150000,5,1,5,10,120,'["dashboard","buses","routes","drivers","agents","trips","bookings","reports","users","audit_logs","settings","branding"]','active',1),
('business','Business Cloud','For growing bus companies with several branches, parcels, cash closing, and operational controls.',2500000,300000,15,5,15,25,500,'["dashboard","buses","routes","drivers","agents","trips","bookings","parcels","reports","cash_closing","reconciliation","maintenance","fuel","notifications","exports","users","audit_logs","settings","branding"]','active',2),
('professional','Professional Multi-Branch','For serious operators that need all Phase 2 controls, exports, backup, branding, and higher limits.',5000000,600000,40,20,50,80,2000,'["dashboard","buses","routes","drivers","agents","trips","bookings","parcels","reports","cash_closing","reconciliation","maintenance","fuel","notifications","exports","backup","users","audit_logs","settings","branding"]','active',3),
('enterprise','Enterprise Custom','For large operators that need high limits, custom support, integrations, and advanced deployment.',12000000,1000000,0,0,0,0,0,'["dashboard","buses","routes","drivers","agents","trips","bookings","parcels","reports","cash_closing","reconciliation","maintenance","fuel","notifications","exports","backup","users","audit_logs","settings","branding","api","custom_integrations","multi_company"]','active',4)
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), setup_fee=VALUES(setup_fee), optional_monthly_fee=VALUES(optional_monthly_fee), max_users=VALUES(max_users), max_branches=VALUES(max_branches), max_buses=VALUES(max_buses), max_routes=VALUES(max_routes), max_monthly_trips=VALUES(max_monthly_trips), features_json=VALUES(features_json), status=VALUES(status), display_order=VALUES(display_order);

INSERT INTO company_settings (setting_key, setting_value) VALUES
('company_name', 'Bus Owner Control Company'),
('company_phone', '+255 700 000 000'),
('company_email', 'info@example.com'),
('company_address', 'Dar es Salaam, Tanzania'),
('receipt_footer', 'Thank you for travelling with us.'),
('whatsapp_country_code', '255'),
('backup_retention_days', '30')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO subscription_license (company_name, license_key, plan, package_code, billing_model, status, start_date, expiry_date, setup_fee_amount, setup_fee_paid, optional_monthly_fee, optional_monthly_enabled, support_status, support_start_date, support_expiry_date, max_users, max_branches, max_buses, max_routes, max_monthly_trips, notes) VALUES
('Bus Owner Control Company', 'DEMO-SAAS-PRO-LOCAL', 'Professional Multi-Branch', 'professional', 'one_time_plus_optional_support', 'trial', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), 5000000, 'partial', 600000, 1, 'active', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 40, 20, 50, 80, 2000, 'Local demo licence for testing the package/licence-ready SaaS model.');

INSERT INTO agents (name, phone, branch, commission_rate, status) VALUES
('Urafiki Counter', '0713000001', 'Urafiki', 3.00, 'active'),
('Posta Counter', '0713000002', 'Posta', 3.00, 'active');

INSERT INTO users (name, email, password_hash, role, agent_id, status) VALUES
('System Owner', 'admin@buspro.local', '$2y$12$O58FSLpFIunlPR4nBXzCCuYHK7TFR5qITmXjH31pYOCoQ9zTIVCxK', 'owner', NULL, 'active'),
('Operations Manager', 'manager@buspro.local', '$2y$12$O58FSLpFIunlPR4nBXzCCuYHK7TFR5qITmXjH31pYOCoQ9zTIVCxK', 'manager', NULL, 'active'),
('Accountant', 'accountant@buspro.local', '$2y$12$O58FSLpFIunlPR4nBXzCCuYHK7TFR5qITmXjH31pYOCoQ9zTIVCxK', 'accountant', NULL, 'active'),
('Urafiki Ticket Agent', 'agent@buspro.local', '$2y$12$O58FSLpFIunlPR4nBXzCCuYHK7TFR5qITmXjH31pYOCoQ9zTIVCxK', 'agent', 1, 'active'),
('Posta Parcel Officer', 'parcel@buspro.local', '$2y$12$O58FSLpFIunlPR4nBXzCCuYHK7TFR5qITmXjH31pYOCoQ9zTIVCxK', 'parcel_officer', 2, 'active');
