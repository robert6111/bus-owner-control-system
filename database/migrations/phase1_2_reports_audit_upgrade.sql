USE bus_owner_control;

-- Phase 1.2: improved reporting filters and detailed audit log context.
-- Run this once after Phase 1.1 if your database already exists.

ALTER TABLE audit_logs
  ADD COLUMN role_snapshot VARCHAR(40) NULL AFTER user_id,
  ADD COLUMN old_values LONGTEXT NULL AFTER description,
  ADD COLUMN new_values LONGTEXT NULL AFTER old_values,
  ADD COLUMN request_method VARCHAR(12) NULL AFTER ip_address,
  ADD COLUMN request_uri VARCHAR(500) NULL AFTER request_method,
  ADD COLUMN user_agent VARCHAR(500) NULL AFTER request_uri,
  ADD INDEX idx_audit_action (action),
  ADD INDEX idx_audit_ip (ip_address);
