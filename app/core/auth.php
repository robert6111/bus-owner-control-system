<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/saas.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $user = null;
    if ($user !== null) {
        return $user;
    }

    $stmt = db()->prepare('SELECT id, name, email, role, status, agent_id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

function login_user(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND status = "active" LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        audit_log_direct('login_failed', 'users', null, 'Failed login attempt for ' . strtolower($email), null, ['email' => strtolower($email)]);
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['last_activity'] = time();

    try {
        db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int)$user['id']]);
    } catch (Throwable $e) {
        // Safe for older databases before migration.
    }

    audit_log('login_success', 'users', (int)$user['id'], 'User logged in successfully', null, ['email' => $user['email'], 'role' => $user['role']]);
    return true;
}

function logout_user(): void
{
    if (!empty($_SESSION['user_id'])) {
        audit_log('logout', 'users', (int)$_SESSION['user_id'], 'User logged out');
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('login.php');
    }

    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > IDLE_TIMEOUT_SECONDS) {
        audit_log('session_timeout', 'users', (int)$_SESSION['user_id'], 'User session expired after idle timeout');
        logout_user();
        session_name(SESSION_NAME);
        session_start();
        flash('warning', 'You were logged out after being idle for security.');
        redirect('login.php');
    }

    $_SESSION['last_activity'] = time();

    if (!current_user()) {
        logout_user();
        redirect('login.php');
    }

    enforce_active_license_for_request();
}

function user_role(): string
{
    $user = current_user();
    return $user['role'] ?? 'guest';
}

function role_label(?string $role): string
{
    $labels = [
        'owner' => 'Owner',
        'admin' => 'Admin',
        'manager' => 'Manager',
        'accountant' => 'Accountant',
        'agent' => 'Ticket Agent',
        'parcel_officer' => 'Parcel Officer',
    ];
    return $labels[$role ?? ''] ?? 'Unknown';
}

function has_role(array $roles): bool
{
    $user = current_user();
    return $user && in_array($user['role'], $roles, true);
}

function require_roles(array $roles): void
{
    require_login();
    if (!has_role($roles)) {
        audit_log('access_denied', 'system', null, 'Access denied for ' . ($_SERVER['REQUEST_URI'] ?? 'unknown URL'), null, ['required_roles' => $roles, 'actual_role' => user_role()]);
        http_response_code(403);
        exit('You do not have permission to access this area.');
    }
}

function can_manage(): bool
{
    return has_role(['owner', 'admin', 'manager']);
}

function can_delete_records(): bool
{
    return has_role(['owner', 'admin']);
}

function can_manage_users(): bool
{
    return has_role(['owner', 'admin']);
}

function can_manage_finance(): bool
{
    return has_role(['owner', 'admin', 'manager', 'accountant']);
}

function can_manage_bookings(): bool
{
    return has_role(['owner', 'admin', 'manager', 'agent']);
}

function can_manage_parcels(): bool
{
    return has_role(['owner', 'admin', 'manager', 'agent', 'parcel_officer']);
}

function can_view_reports(): bool
{
    return has_role(['owner', 'admin', 'manager', 'accountant', 'agent', 'parcel_officer']);
}

function require_manager(): void
{
    require_roles(['owner', 'admin', 'manager']);
}

function user_agent_id(): ?int
{
    $user = current_user();
    if (!$user || empty($user['agent_id'])) {
        return null;
    }
    return (int)$user['agent_id'];
}

function is_branch_scoped_user(): bool
{
    return has_role(['agent', 'parcel_officer']);
}

function require_branch_assignment(): void
{
    if (is_branch_scoped_user() && !user_agent_id()) {
        audit_log('access_denied', 'users', (int)(current_user()['id'] ?? 0), 'Branch-scoped user has no assigned branch/agent');
        http_response_code(403);
        exit('Your account is not attached to an agent/branch. Ask the owner/admin to assign your account to a branch.');
    }
}

function scoped_agent_where(string $tableAlias = ''): array
{
    $prefix = $tableAlias ? $tableAlias . '.' : '';
    if (is_branch_scoped_user()) {
        require_branch_assignment();
        return [' AND ' . $prefix . 'agent_id = ? ', [user_agent_id()]];
    }
    return ['', []];
}

function ensure_record_belongs_to_current_agent(string $table, int $recordId): void
{
    if (!is_branch_scoped_user()) {
        return;
    }
    require_branch_assignment();
    $allowedTables = ['bookings', 'parcels'];
    if (!in_array($table, $allowedTables, true)) {
        audit_log('access_denied', $table, $recordId, 'Scoped access attempted on unsupported table');
        http_response_code(403);
        exit('Scoped access is not available for this record type.');
    }
    $stmt = db()->prepare("SELECT agent_id FROM {$table} WHERE id = ? LIMIT 1");
    $stmt->execute([$recordId]);
    $agentId = $stmt->fetchColumn();
    if ((int)$agentId !== (int)user_agent_id()) {
        audit_log('access_denied', $table, $recordId, 'Branch user attempted to access another branch record', null, ['current_agent_id' => user_agent_id(), 'record_agent_id' => $agentId]);
        http_response_code(403);
        exit('You can only access records from your own assigned branch/agent.');
    }
}

function audit_log(string $action, string $tableName, ?int $recordId = null, string $description = '', ?array $oldValues = null, ?array $newValues = null): void
{
    $user = current_user();
    audit_log_direct(
        $action,
        $tableName,
        $recordId,
        $description,
        $oldValues,
        $newValues,
        $user['id'] ?? null,
        $user['role'] ?? null
    );
}

function audit_log_direct(string $action, string $tableName, ?int $recordId = null, string $description = '', ?array $oldValues = null, ?array $newValues = null, ?int $userId = null, ?string $roleSnapshot = null): void
{
    try {
        $columns = audit_log_columns();
        if (!$columns) {
            return;
        }

        $payload = [
            'user_id' => $userId,
            'role_snapshot' => $roleSnapshot,
            'action' => $action,
            'table_name' => $tableName,
            'record_id' => $recordId,
            'description' => $description,
            'old_values' => audit_json($oldValues),
            'new_values' => audit_json($newValues),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'request_uri' => audit_truncate($_SERVER['REQUEST_URI'] ?? null, 500),
            'user_agent' => audit_truncate($_SERVER['HTTP_USER_AGENT'] ?? null, 500),
        ];

        $insert = [];
        foreach ($payload as $column => $value) {
            if (in_array($column, $columns, true)) {
                $insert[$column] = $value;
            }
        }
        if (!$insert) {
            return;
        }

        $fieldList = implode(',', array_keys($insert));
        $placeholders = implode(',', array_fill(0, count($insert), '?'));
        $stmt = db()->prepare("INSERT INTO audit_logs ({$fieldList}) VALUES ({$placeholders})");
        $stmt->execute(array_values($insert));
    } catch (Throwable $e) {
        // Audit logging should never break normal business operations.
    }
}

function audit_log_columns(): array
{
    static $columns = null;
    if ($columns !== null) {
        return $columns;
    }
    try {
        $rows = db()->query('SHOW COLUMNS FROM audit_logs')->fetchAll();
        $columns = array_map(fn($row) => $row['Field'], $rows);
    } catch (Throwable $e) {
        $columns = [];
    }
    return $columns;
}

function audit_json(?array $values): ?string
{
    if ($values === null) {
        return null;
    }
    $clean = audit_redact($values);
    $json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return audit_truncate($json ?: null, 65000);
}

function audit_redact(array $values): array
{
    $hiddenKeys = ['password', 'password_hash', 'csrf_token'];
    $clean = [];
    foreach ($values as $key => $value) {
        if (in_array((string)$key, $hiddenKeys, true)) {
            $clean[$key] = '[redacted]';
        } elseif (is_array($value)) {
            $clean[$key] = audit_redact($value);
        } else {
            $clean[$key] = $value;
        }
    }
    return $clean;
}

function audit_truncate(?string $value, int $limit): ?string
{
    if ($value === null) {
        return null;
    }
    return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit) : $value;
}

function audit_record_snapshot(string $table, int $recordId): ?array
{
    $allowedTables = ['agents','buses','routes','drivers','trips','bookings','parcels','expenses','users','cash_closings','maintenance_records','fuel_logs','notification_logs','backup_logs','subscription_license','company_settings','package_tiers'];
    if (!in_array($table, $allowedTables, true) || $recordId <= 0) {
        return null;
    }
    try {
        $stmt = db()->prepare("SELECT * FROM {$table} WHERE id = ? LIMIT 1");
        $stmt->execute([$recordId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function audit_changed_fields(?array $oldValues, ?array $newValues): array
{
    if (!$oldValues || !$newValues) {
        return $newValues ?? [];
    }
    $changes = [];
    foreach ($newValues as $key => $newValue) {
        if (array_key_exists($key, $oldValues) && (string)$oldValues[$key] !== (string)$newValue) {
            $changes[$key] = ['from' => $oldValues[$key], 'to' => $newValue];
        }
    }
    return $changes;
}
