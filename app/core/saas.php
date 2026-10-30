<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function saas_default_packages(): array
{
    return [
        'starter' => [
            'name' => 'Starter Local',
            'description' => 'For one small office that mainly needs trips, passengers, basic reports, and strict records.',
            'setup_fee' => 1500000,
            'optional_monthly_fee' => 150000,
            'max_users' => 5,
            'max_branches' => 1,
            'max_buses' => 5,
            'max_routes' => 10,
            'max_monthly_trips' => 120,
            'features' => [
                'dashboard','buses','routes','drivers','agents','trips','bookings','reports','users','audit_logs','settings','branding'
            ],
        ],
        'business' => [
            'name' => 'Business Cloud',
            'description' => 'For growing bus companies with several branches, parcels, cash closing, and operational controls.',
            'setup_fee' => 2500000,
            'optional_monthly_fee' => 300000,
            'max_users' => 15,
            'max_branches' => 5,
            'max_buses' => 15,
            'max_routes' => 25,
            'max_monthly_trips' => 500,
            'features' => [
                'dashboard','buses','routes','drivers','agents','trips','bookings','parcels','reports','cash_closing','reconciliation','maintenance','fuel','notifications','exports','users','audit_logs','settings','branding'
            ],
        ],
        'professional' => [
            'name' => 'Professional Multi-Branch',
            'description' => 'For serious operators that need all Phase 2 controls, exports, backup, branding, and higher limits.',
            'setup_fee' => 5000000,
            'optional_monthly_fee' => 600000,
            'max_users' => 40,
            'max_branches' => 20,
            'max_buses' => 50,
            'max_routes' => 80,
            'max_monthly_trips' => 2000,
            'features' => [
                'dashboard','buses','routes','drivers','agents','trips','bookings','parcels','reports','cash_closing','reconciliation','maintenance','fuel','notifications','exports','backup','users','audit_logs','settings','branding'
            ],
        ],
        'enterprise' => [
            'name' => 'Enterprise Custom',
            'description' => 'For large operators that need high limits, custom support, integrations, and advanced deployment.',
            'setup_fee' => 12000000,
            'optional_monthly_fee' => 1000000,
            'max_users' => 0,
            'max_branches' => 0,
            'max_buses' => 0,
            'max_routes' => 0,
            'max_monthly_trips' => 0,
            'features' => [
                'dashboard','buses','routes','drivers','agents','trips','bookings','parcels','reports','cash_closing','reconciliation','maintenance','fuel','notifications','exports','backup','users','audit_logs','settings','branding','api','custom_integrations','multi_company'
            ],
        ],
    ];
}

function saas_feature_labels(): array
{
    return [
        'dashboard' => 'Dashboard',
        'buses' => 'Buses / fleet',
        'routes' => 'Routes',
        'drivers' => 'Drivers',
        'agents' => 'Agents / branches',
        'trips' => 'Trips / schedules',
        'bookings' => 'Passenger seats / tickets',
        'parcels' => 'Parcel / cargo tracking',
        'reports' => 'Reports',
        'cash_closing' => 'Daily cash closing',
        'reconciliation' => 'Agent reconciliation',
        'maintenance' => 'Maintenance records',
        'fuel' => 'Fuel tracking',
        'notifications' => 'WhatsApp notification center',
        'exports' => 'CSV exports',
        'backup' => 'Manual SQL backup',
        'users' => 'Users and role access',
        'audit_logs' => 'Audit logs',
        'settings' => 'Company settings',
        'branding' => 'Company branding on documents',
        'api' => 'API / external integrations',
        'custom_integrations' => 'Custom integrations',
        'multi_company' => 'Multi-company deployment support',
    ];
}

function saas_safe_identifier(string $name): string
{
    return preg_replace('/[^a-zA-Z0-9_]/', '', $name);
}

function saas_table_exists(string $table): bool
{
    $table = saas_safe_identifier($table);
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

function saas_column_exists(string $table, string $column): bool
{
    $table = saas_safe_identifier($table);
    $column = saas_safe_identifier($column);
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

function package_tiers(): array
{
    $defaults = saas_default_packages();
    if (!saas_table_exists('package_tiers')) {
        return $defaults;
    }
    try {
        $rows = db()->query('SELECT * FROM package_tiers WHERE status="active" ORDER BY display_order, id')->fetchAll();
        if (!$rows) return $defaults;
        $tiers = [];
        foreach ($rows as $row) {
            $features = json_decode((string)($row['features_json'] ?? '[]'), true);
            if (!is_array($features)) $features = [];
            $tiers[$row['code']] = [
                'name' => $row['name'],
                'description' => $row['description'] ?? '',
                'setup_fee' => (float)$row['setup_fee'],
                'optional_monthly_fee' => (float)$row['optional_monthly_fee'],
                'max_users' => (int)$row['max_users'],
                'max_branches' => (int)$row['max_branches'],
                'max_buses' => (int)$row['max_buses'],
                'max_routes' => (int)$row['max_routes'],
                'max_monthly_trips' => (int)$row['max_monthly_trips'],
                'features' => $features,
            ];
        }
        return $tiers ?: $defaults;
    } catch (Throwable $e) {
        return $defaults;
    }
}

function package_tier(?string $code = null): array
{
    $tiers = package_tiers();
    $code = $code ?: current_package_code();
    return $tiers[$code] ?? reset($tiers);
}

function current_package_code(): string
{
    $license = saas_current_license();
    if ($license && !empty($license['package_code'])) {
        return (string)$license['package_code'];
    }
    if ($license && !empty($license['plan'])) {
        $plan = strtolower((string)$license['plan']);
        foreach (array_keys(saas_default_packages()) as $code) {
            if (strpos($plan, $code) !== false) return $code;
        }
    }
    return 'professional';
}

function saas_current_license(): ?array
{
    try {
        if (!saas_table_exists('subscription_license')) return null;
        return db()->query('SELECT * FROM subscription_license ORDER BY id DESC LIMIT 1')->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function saas_license_status(?array $license = null): array
{
    $license = $license ?: saas_current_license();
    if (!$license) {
        return ['status' => 'missing', 'is_valid' => false, 'label' => 'No licence', 'class' => 'danger', 'days_left' => null];
    }
    $status = $license['status'] ?? 'trial';
    $daysLeft = null;
    if (!empty($license['expiry_date'])) {
        $today = new DateTimeImmutable(date('Y-m-d'));
        $expiry = new DateTimeImmutable($license['expiry_date']);
        $daysLeft = (int)$today->diff($expiry)->format('%r%a');
        if ($daysLeft < 0 && !in_array($status, ['expired', 'suspended'], true)) {
            $status = 'expired';
        }
    }
    $valid = in_array($status, ['trial', 'active'], true);
    $classes = ['trial' => 'warning', 'active' => 'success', 'expired' => 'danger', 'suspended' => 'danger', 'missing' => 'danger'];
    $label = ucfirst($status);
    if ($daysLeft !== null) {
        $label .= $daysLeft >= 0 ? ' · ' . $daysLeft . ' days left' : ' · expired ' . abs($daysLeft) . ' days ago';
    }
    return ['status' => $status, 'is_valid' => $valid, 'label' => $label, 'class' => $classes[$status] ?? 'warning', 'days_left' => $daysLeft];
}

function saas_support_status(?array $license = null): array
{
    $license = $license ?: saas_current_license();
    if (!$license || empty($license['optional_monthly_enabled'])) {
        return ['enabled' => false, 'status' => 'off', 'label' => 'Optional support off', 'class' => 'muted'];
    }
    $status = $license['support_status'] ?? 'active';
    $label = ucfirst($status) . ' support';
    $class = $status === 'active' ? 'success' : 'warning';
    if (!empty($license['support_expiry_date'])) {
        $today = new DateTimeImmutable(date('Y-m-d'));
        $expiry = new DateTimeImmutable($license['support_expiry_date']);
        $days = (int)$today->diff($expiry)->format('%r%a');
        if ($days < 0) { $status = 'expired'; $class = 'danger'; }
        $label .= $days >= 0 ? ' · ' . $days . ' days left' : ' · expired ' . abs($days) . ' days ago';
    }
    return ['enabled' => true, 'status' => $status, 'label' => $label, 'class' => $class];
}

function feature_enabled(string $feature): bool
{
    $tier = package_tier();
    return in_array($feature, $tier['features'] ?? [], true);
}

function require_feature(string $feature): void
{
    if (feature_enabled($feature)) return;
    if (function_exists('audit_log')) {
        audit_log('feature_blocked', 'subscription_license', null, 'Blocked feature access: ' . $feature, null, ['feature' => $feature, 'package' => current_package_code()]);
    }
    http_response_code(403);
    $labels = saas_feature_labels();
    exit('This feature is not available in the current package: ' . ($labels[$feature] ?? $feature) . '. Upgrade the package to unlock it.');
}

function package_limit(string $resource): int
{
    $tier = package_tier();
    return (int)($tier['max_' . $resource] ?? 0);
}

function package_usage_counts(): array
{
    $counts = ['users'=>0,'branches'=>0,'buses'=>0,'routes'=>0,'monthly_trips'=>0];
    try { $counts['users'] = (int)db()->query('SELECT COUNT(*) FROM users WHERE status="active"')->fetchColumn(); } catch (Throwable $e) {}
    try { $counts['branches'] = (int)db()->query('SELECT COUNT(*) FROM agents WHERE status="active"')->fetchColumn(); } catch (Throwable $e) {}
    try { $counts['buses'] = (int)db()->query('SELECT COUNT(*) FROM buses WHERE status<>"inactive"')->fetchColumn(); } catch (Throwable $e) {}
    try { $counts['routes'] = (int)db()->query('SELECT COUNT(*) FROM routes WHERE status="active"')->fetchColumn(); } catch (Throwable $e) {}
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM trips WHERE departure_date BETWEEN ? AND ? AND status<>"cancelled"');
        $st->execute([date('Y-m-01'), date('Y-m-t')]);
        $counts['monthly_trips'] = (int)$st->fetchColumn();
    } catch (Throwable $e) {}
    return $counts;
}

function limit_label(int $limit): string
{
    return $limit <= 0 ? 'Unlimited' : number_format($limit);
}

function package_limit_status(string $resource): array
{
    $limit = package_limit($resource);
    $usage = package_usage_counts()[$resource] ?? 0;
    $remaining = $limit <= 0 ? null : max(0, $limit - $usage);
    $percent = $limit <= 0 ? 0 : min(100, round(($usage / max(1, $limit)) * 100));
    return ['resource'=>$resource,'usage'=>$usage,'limit'=>$limit,'remaining'=>$remaining,'percent'=>$percent,'unlimited'=>$limit<=0];
}

function require_limit_available(string $resource): void
{
    $status = package_limit_status($resource);
    if ($status['unlimited'] || $status['usage'] < $status['limit']) return;
    if (function_exists('audit_log')) {
        audit_log('limit_blocked', 'subscription_license', null, 'Package limit reached: ' . $resource, null, $status);
    }
    http_response_code(403);
    exit('Package limit reached for ' . str_replace('_', ' ', $resource) . '. Current package allows ' . limit_label($status['limit']) . '. Upgrade the package to add more.');
}

function package_badge(): array
{
    $tier = package_tier();
    $license = saas_current_license();
    $state = saas_license_status($license);
    return [
        'package' => $tier['name'] ?? current_package_code(),
        'status' => $state['label'],
        'class' => $state['class'],
    ];
}

function saas_allowed_when_inactive(): array
{
    return ['login.php','logout.php','packages.php','settings.php','audit_logs.php'];
}

function enforce_active_license_for_request(): void
{
    $state = saas_license_status();
    if ($state['is_valid']) return;
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($script, saas_allowed_when_inactive(), true)) return;

    if (function_exists('has_role') && has_role(['owner','admin'])) {
        if (function_exists('flash')) {
            flash('danger', 'Licence is not active. Operational pages are blocked until the package/licence is fixed.');
        }
        if (function_exists('audit_log')) {
            audit_log('license_blocked', 'subscription_license', null, 'Inactive licence redirected to package management');
        }
        redirect('packages.php');
    }
    http_response_code(403);
    exit('This installation licence is not active. Ask the owner/admin to activate or renew the package.');
}

function saas_check_setup_health(): array
{
    $issues = [];
    foreach (['package_tiers','subscription_license','audit_logs'] as $table) {
        if (!saas_table_exists($table)) $issues[] = 'Missing table: ' . $table;
    }
    foreach (['package_code','billing_model','setup_fee_amount','optional_monthly_fee','optional_monthly_enabled','support_status','support_expiry_date','max_buses','max_routes','max_monthly_trips'] as $column) {
        if (saas_table_exists('subscription_license') && !saas_column_exists('subscription_license', $column)) {
            $issues[] = 'Missing subscription_license column: ' . $column;
        }
    }
    return $issues;
}
