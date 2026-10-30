<?php
require_once __DIR__ . '/../app/core/db.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function repair_db_name(): string
{
    try {
        return (string)db()->query('SELECT DATABASE()')->fetchColumn();
    } catch (Throwable $e) {
        return DB_NAME;
    }
}

function repair_safe_identifier(string $name): string
{
    return preg_replace('/[^a-zA-Z0-9_]/', '', $name);
}

function repair_table_exists(string $table): bool
{
    $table = repair_safe_identifier($table);
    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        try {
            $stmt = db()->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $inner) {
            return false;
        }
    }
}

function repair_column_exists(string $table, string $column): bool
{
    $table = repair_safe_identifier($table);
    $column = repair_safe_identifier($column);
    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        try {
            $stmt = db()->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE ?');
            $stmt->execute([$column]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $inner) {
            return false;
        }
    }
}

function repair_index_exists(string $table, string $index): bool
{
    $table = repair_safe_identifier($table);
    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
        $stmt->execute([$table, $index]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        try {
            $stmt = db()->prepare('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?');
            $stmt->execute([$index]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $inner) {
            return false;
        }
    }
}

function repair_run(string $sql, array &$messages, string $label): void
{
    try {
        db()->exec($sql);
        $messages[] = ['ok', $label];
    } catch (Throwable $e) {
        $message = $e->getMessage();
        if (stripos($message, 'Duplicate column name') !== false || stripos($message, 'Duplicate key name') !== false || strpos($message, '1060') !== false || strpos($message, '1061') !== false || strpos($message, '42S21') !== false) {
            $messages[] = ['skip', $label . ' already exists'];
            return;
        }
        $messages[] = ['error', $label . ' failed: ' . $message];
    }
}

function repair_add_column(string $table, string $column, string $definition, array &$messages): void
{
    if (repair_column_exists($table, $column)) {
        $messages[] = ['skip', $table . '.' . $column . ' already exists'];
        return;
    }
    repair_run('ALTER TABLE `' . repair_safe_identifier($table) . '` ADD COLUMN ' . $definition, $messages, 'Added ' . $table . '.' . $column);
}

function repair_add_index(string $table, string $index, string $definition, array &$messages): void
{
    if (repair_index_exists($table, $index)) {
        $messages[] = ['skip', 'Index ' . $index . ' already exists'];
        return;
    }
    repair_run('ALTER TABLE `' . repair_safe_identifier($table) . '` ADD ' . $definition, $messages, 'Added index ' . $index);
}

function repair_missing_items(): array
{
    $items = [];
    foreach (['package_tiers', 'subscription_license', 'audit_logs'] as $table) {
        if (!repair_table_exists($table)) {
            $items[] = 'Missing table: ' . $table;
        }
    }
    $columns = [
        'subscription_license' => ['package_code','billing_model','setup_fee_amount','setup_fee_paid','optional_monthly_fee','optional_monthly_enabled','support_status','support_start_date','support_expiry_date','max_buses','max_routes','max_monthly_trips'],
        'audit_logs' => ['role_snapshot','old_values','new_values','ip_address','request_method','request_uri','user_agent'],
    ];
    foreach ($columns as $table => $cols) {
        if (!repair_table_exists($table)) {
            continue;
        }
        foreach ($cols as $column) {
            if (!repair_column_exists($table, $column)) {
                $items[] = 'Missing column: ' . $table . '.' . $column;
            }
        }
    }
    return $items;
}

function run_saas_repair(): array
{
    $messages = [];

    repair_run("CREATE TABLE IF NOT EXISTS company_settings (
      setting_key VARCHAR(80) PRIMARY KEY,
      setting_value TEXT NULL,
      updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $messages, 'company_settings table ready');

    repair_run("CREATE TABLE IF NOT EXISTS package_tiers (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $messages, 'package_tiers table ready');

    repair_run("CREATE TABLE IF NOT EXISTS subscription_license (
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
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $messages, 'subscription_license table ready');

    repair_run("CREATE TABLE IF NOT EXISTS audit_logs (
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
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $messages, 'audit_logs table ready');

    // Column repairs for older installations. These are checked one by one for MySQL/MariaDB compatibility.
    repair_add_column('subscription_license', 'package_code', "package_code VARCHAR(40) NOT NULL DEFAULT 'professional' AFTER plan", $messages);
    repair_add_column('subscription_license', 'billing_model', "billing_model ENUM('one_time_setup','one_time_plus_optional_support','recurring_subscription') NOT NULL DEFAULT 'one_time_plus_optional_support' AFTER package_code", $messages);
    repair_add_column('subscription_license', 'setup_fee_amount', "setup_fee_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER expiry_date", $messages);
    repair_add_column('subscription_license', 'setup_fee_paid', "setup_fee_paid ENUM('no','partial','yes') NOT NULL DEFAULT 'no' AFTER setup_fee_amount", $messages);
    repair_add_column('subscription_license', 'optional_monthly_fee', "optional_monthly_fee DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER setup_fee_paid", $messages);
    repair_add_column('subscription_license', 'optional_monthly_enabled', "optional_monthly_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER optional_monthly_fee", $messages);
    repair_add_column('subscription_license', 'support_status', "support_status ENUM('off','active','paused','expired') NOT NULL DEFAULT 'off' AFTER optional_monthly_enabled", $messages);
    repair_add_column('subscription_license', 'support_start_date', "support_start_date DATE NULL AFTER support_status", $messages);
    repair_add_column('subscription_license', 'support_expiry_date', "support_expiry_date DATE NULL AFTER support_start_date", $messages);
    repair_add_column('subscription_license', 'max_buses', "max_buses INT NOT NULL DEFAULT 50 AFTER max_branches", $messages);
    repair_add_column('subscription_license', 'max_routes', "max_routes INT NOT NULL DEFAULT 80 AFTER max_buses", $messages);
    repair_add_column('subscription_license', 'max_monthly_trips', "max_monthly_trips INT NOT NULL DEFAULT 2000 AFTER max_routes", $messages);

    repair_add_column('audit_logs', 'role_snapshot', "role_snapshot VARCHAR(40) NULL AFTER user_id", $messages);
    repair_add_column('audit_logs', 'old_values', "old_values LONGTEXT NULL AFTER description", $messages);
    repair_add_column('audit_logs', 'new_values', "new_values LONGTEXT NULL AFTER old_values", $messages);
    repair_add_column('audit_logs', 'ip_address', "ip_address VARCHAR(80) NULL AFTER new_values", $messages);
    repair_add_column('audit_logs', 'request_method', "request_method VARCHAR(12) NULL AFTER ip_address", $messages);
    repair_add_column('audit_logs', 'request_uri', "request_uri VARCHAR(500) NULL AFTER request_method", $messages);
    repair_add_column('audit_logs', 'user_agent', "user_agent VARCHAR(500) NULL AFTER request_uri", $messages);

    repair_add_index('subscription_license', 'idx_subscription_package', 'INDEX idx_subscription_package (package_code)', $messages);
    repair_add_index('subscription_license', 'idx_subscription_status', 'INDEX idx_subscription_status (status)', $messages);
    repair_add_index('audit_logs', 'idx_audit_created', 'INDEX idx_audit_created (created_at)', $messages);
    repair_add_index('audit_logs', 'idx_audit_action', 'INDEX idx_audit_action (action)', $messages);
    repair_add_index('audit_logs', 'idx_audit_table_record', 'INDEX idx_audit_table_record (table_name, record_id)', $messages);
    repair_add_index('audit_logs', 'idx_audit_user', 'INDEX idx_audit_user (user_id)', $messages);
    repair_add_index('audit_logs', 'idx_audit_ip', 'INDEX idx_audit_ip (ip_address)', $messages);

    $packages = [
        ['starter','Starter Local','For one small office that mainly needs trips, passengers, basic reports, and strict records.',1500000,150000,5,1,5,10,120,'["dashboard","buses","routes","drivers","agents","trips","bookings","reports","users","audit_logs","settings","branding"]',1],
        ['business','Business Cloud','For growing bus companies with several branches, parcels, cash closing, and operational controls.',2500000,300000,15,5,15,25,500,'["dashboard","buses","routes","drivers","agents","trips","bookings","parcels","reports","cash_closing","reconciliation","maintenance","fuel","notifications","exports","users","audit_logs","settings","branding"]',2],
        ['professional','Professional Multi-Branch','For serious operators that need all Phase 2 controls, exports, backup, branding, and higher limits.',5000000,600000,40,20,50,80,2000,'["dashboard","buses","routes","drivers","agents","trips","bookings","parcels","reports","cash_closing","reconciliation","maintenance","fuel","notifications","exports","backup","users","audit_logs","settings","branding"]',3],
        ['enterprise','Enterprise Custom','For large operators that need high limits, custom support, integrations, and advanced deployment.',12000000,1000000,0,0,0,0,0,'["dashboard","buses","routes","drivers","agents","trips","bookings","parcels","reports","cash_closing","reconciliation","maintenance","fuel","notifications","exports","backup","users","audit_logs","settings","branding","api","custom_integrations","multi_company"]',4],
    ];

    try {
        $stmt = db()->prepare("INSERT INTO package_tiers (code,name,description,setup_fee,optional_monthly_fee,max_users,max_branches,max_buses,max_routes,max_monthly_trips,features_json,status,display_order)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,'active',?)
            ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), setup_fee=VALUES(setup_fee), optional_monthly_fee=VALUES(optional_monthly_fee), max_users=VALUES(max_users), max_branches=VALUES(max_branches), max_buses=VALUES(max_buses), max_routes=VALUES(max_routes), max_monthly_trips=VALUES(max_monthly_trips), features_json=VALUES(features_json), status='active', display_order=VALUES(display_order)");
        foreach ($packages as $pkg) {
            $stmt->execute($pkg);
        }
        $messages[] = ['ok', 'Default package tiers inserted/updated'];
    } catch (Throwable $e) {
        $messages[] = ['error', 'Package tier insert failed: ' . $e->getMessage()];
    }

    $settings = [
        'company_name' => 'Bus Owner Control Company',
        'company_phone' => '+255 700 000 000',
        'company_email' => 'info@example.com',
        'company_address' => 'Dar es Salaam, Tanzania',
        'receipt_footer' => 'Thank you for travelling with us.',
        'whatsapp_country_code' => '255',
        'backup_retention_days' => '30',
    ];
    try {
        $stmt = db()->prepare('INSERT INTO company_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = setting_value');
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value]);
        }
        $messages[] = ['ok', 'Company settings defaults checked'];
    } catch (Throwable $e) {
        $messages[] = ['error', 'Company settings insert failed: ' . $e->getMessage()];
    }

    try {
        $exists = (int)db()->query('SELECT COUNT(*) FROM subscription_license')->fetchColumn();
        if ($exists === 0) {
            $stmt = db()->prepare("INSERT INTO subscription_license (company_name, license_key, plan, package_code, billing_model, status, start_date, expiry_date, setup_fee_amount, setup_fee_paid, optional_monthly_fee, optional_monthly_enabled, support_status, support_start_date, support_expiry_date, max_users, max_branches, max_buses, max_routes, max_monthly_trips, notes)
                VALUES ('Bus Owner Control Company','DEMO-SAAS-PRO-LOCAL','Professional Multi-Branch','professional','one_time_plus_optional_support','trial',CURDATE(),DATE_ADD(CURDATE(), INTERVAL 90 DAY),5000000,'partial',600000,1,'active',CURDATE(),DATE_ADD(CURDATE(), INTERVAL 30 DAY),40,20,50,80,2000,'Local demo licence for testing the package/licence-ready model.')");
            $stmt->execute();
            $messages[] = ['ok', 'Demo subscription/licence inserted'];
        } else {
            $messages[] = ['skip', 'subscription_license already has a record'];
        }
    } catch (Throwable $e) {
        $messages[] = ['error', 'Subscription/licence insert failed: ' . $e->getMessage()];
    }

    try {
        db()->exec("UPDATE subscription_license sl
            LEFT JOIN package_tiers pt ON pt.code = COALESCE(NULLIF(sl.package_code,''), 'professional')
            SET sl.package_code = COALESCE(NULLIF(sl.package_code,''), 'professional'),
                sl.plan = COALESCE(pt.name, sl.plan),
                sl.billing_model = COALESCE(NULLIF(sl.billing_model,''), 'one_time_plus_optional_support'),
                sl.setup_fee_amount = IF(sl.setup_fee_amount = 0, COALESCE(pt.setup_fee, 5000000), sl.setup_fee_amount),
                sl.optional_monthly_fee = IF(sl.optional_monthly_fee = 0, COALESCE(pt.optional_monthly_fee, 600000), sl.optional_monthly_fee),
                sl.max_users = IF(sl.max_users = 0, COALESCE(pt.max_users, 40), sl.max_users),
                sl.max_branches = IF(sl.max_branches = 0, COALESCE(pt.max_branches, 20), sl.max_branches),
                sl.max_buses = IF(sl.max_buses = 0, COALESCE(pt.max_buses, 50), sl.max_buses),
                sl.max_routes = IF(sl.max_routes = 0, COALESCE(pt.max_routes, 80), sl.max_routes),
                sl.max_monthly_trips = IF(sl.max_monthly_trips = 0, COALESCE(pt.max_monthly_trips, 2000), sl.max_monthly_trips)");
        $messages[] = ['ok', 'Licence package values normalized'];
    } catch (Throwable $e) {
        $messages[] = ['error', 'Licence normalization failed: ' . $e->getMessage()];
    }

    try {
        db()->prepare("INSERT INTO audit_logs (action, table_name, description, ip_address, request_method, request_uri, user_agent) VALUES ('saas_repair', 'system', 'SaaS repair tool executed', ?, ?, ?, ?)")
            ->execute([$_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['REQUEST_METHOD'] ?? null, $_SERVER['REQUEST_URI'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
        $messages[] = ['ok', 'Repair action logged'];
    } catch (Throwable $e) {
        $messages[] = ['skip', 'Audit log insert skipped: ' . $e->getMessage()];
    }

    return $messages;
}

$before = repair_missing_items();
$messages = [];
$ran = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ran = true;
    $messages = run_saas_repair();
}
$after = repair_missing_items();
$dbName = repair_db_name();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SaaS Database Repair</title>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;background:#f5f7fb;color:#162033;margin:0;padding:30px;}
        .card{max-width:980px;margin:0 auto;background:#fff;border:1px solid #e4e8f0;border-radius:14px;padding:24px;box-shadow:0 10px 25px rgba(20,30,50,.08);}
        h1{margin-top:0;font-size:26px}.muted{color:#667085}.badge{display:inline-block;padding:5px 10px;border-radius:999px;background:#eef2ff;margin-right:8px}
        .ok{color:#027a48}.error{color:#b42318}.skip{color:#7a5d00}.box{background:#f8fafc;border:1px solid #e4e8f0;padding:14px;border-radius:10px;margin:14px 0;}
        button,.btn{display:inline-block;background:#111827;color:#fff;border:0;padding:12px 16px;border-radius:10px;text-decoration:none;cursor:pointer;font-weight:700}
        .btn.secondary{background:#475467} code{background:#eef2f6;padding:2px 6px;border-radius:6px} li{margin:6px 0}
    </style>
</head>
<body>
<div class="card">
    <h1>SaaS Database Repair</h1>
    <p class="muted">This page repairs the SaaS/package tables in the exact database your app is connected to.</p>
    <p><span class="badge">Connected database: <strong><?= h($dbName) ?></strong></span><span class="badge">Expected DB_NAME: <strong><?= h(DB_NAME) ?></strong></span></p>

    <?php if ($before): ?>
        <div class="box">
            <strong>Before repair, missing items were:</strong>
            <ul><?php foreach ($before as $item): ?><li class="error"><?= h($item) ?></li><?php endforeach; ?></ul>
        </div>
    <?php else: ?>
        <div class="box ok"><strong>Before repair:</strong> required SaaS tables/columns were already present.</div>
    <?php endif; ?>

    <?php if ($ran): ?>
        <div class="box">
            <strong>Repair results:</strong>
            <ul>
                <?php foreach ($messages as [$type, $message]): ?>
                    <li class="<?= h($type) ?>"><?= h(strtoupper($type)) ?>: <?= h($message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php if ($after): ?>
            <div class="box">
                <strong>Still missing after repair:</strong>
                <ul><?php foreach ($after as $item): ?><li class="error"><?= h($item) ?></li><?php endforeach; ?></ul>
                <p class="muted">If this remains, check that MySQL user <code><?= h(DB_USER) ?></code> has CREATE/ALTER permissions on database <code><?= h($dbName) ?></code>.</p>
            </div>
        <?php else: ?>
            <div class="box ok"><strong>Success:</strong> SaaS tables and required columns are now present. Refresh the main system with Ctrl + F5.</div>
        <?php endif; ?>
    <?php endif; ?>

    <form method="post" onsubmit="return confirm('Run SaaS database repair on <?= h($dbName) ?>?');">
        <button type="submit">Run SaaS Repair Now</button>
        <a class="btn secondary" href="dashboard.php">Back to Dashboard</a>
        <a class="btn secondary" href="packages.php">Open Packages</a>
    </form>

    <div class="box muted">
        <strong>After it works:</strong> delete <code>public/saas_repair.php</code> before real production deployment, because repair tools should not stay public on a live server.
    </div>
</div>
</body>
</html>
