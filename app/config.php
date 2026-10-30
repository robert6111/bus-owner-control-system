<?php
// Application configuration
const APP_NAME = 'Bus Owner Control System';
const APP_VERSION = '2.5.2 SaaS Repair Ready';

// Database configuration: set DB_HOST, DB_NAME, DB_USER and DB_PASS as environment variables,
// or change the local XAMPP defaults below. Never commit a real production password.
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'bus_owner_control');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
const DB_CHARSET = 'utf8mb4';

// Security/session configuration
const SESSION_NAME = 'bus_owner_session';
const IDLE_TIMEOUT_SECONDS = 1800; // 30 minutes

// Business configuration
const CURRENCY = 'TZS';
