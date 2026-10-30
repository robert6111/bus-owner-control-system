<?php
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/saas.php';
require_roles(['owner','admin']);
require_feature('audit_logs');

$pdo = db();
$from = get_value('from', date('Y-m-01'));
$to = get_value('to', date('Y-m-d'));
$userId = (int)get_value('user_id', '0');
$role = get_value('role', '');
$actionFilter = get_value('action_filter', '');
$tableFilter = get_value('table_filter', '');
$recordId = (int)get_value('record_id', '0');
$ip = get_value('ip', '');
$keyword = get_value('keyword', '');
$limit = (int)get_value('limit', '500');
if (!in_array($limit, [100,250,500,1000,2000], true)) { $limit = 500; }

if (!empty($_GET)) {
    audit_log('filter', 'audit_logs', null, 'Audit logs filtered', null, [
        'from' => $from,
        'to' => $to,
        'user_id' => $userId ?: null,
        'role' => $role ?: null,
        'action_filter' => $actionFilter ?: null,
        'table_filter' => $tableFilter ?: null,
        'record_id' => $recordId ?: null,
        'ip' => $ip ?: null,
        'keyword' => $keyword ?: null,
        'limit' => $limit,
    ]);
}

$users = $pdo->query('SELECT id,name,email,role FROM users ORDER BY name')->fetchAll();
$actions = $pdo->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll();
$tables = $pdo->query('SELECT DISTINCT table_name FROM audit_logs ORDER BY table_name')->fetchAll();
$roles = ['owner','admin','manager','accountant','agent','parcel_officer'];

$where = ['DATE(l.created_at) BETWEEN ? AND ?'];
$params = [$from, $to];
if ($userId > 0) { $where[] = 'l.user_id = ?'; $params[] = $userId; }
if ($role !== '') { $where[] = 'COALESCE(l.role_snapshot,u.role) = ?'; $params[] = $role; }
if ($actionFilter !== '') { $where[] = 'l.action = ?'; $params[] = $actionFilter; }
if ($tableFilter !== '') { $where[] = 'l.table_name = ?'; $params[] = $tableFilter; }
if ($recordId > 0) { $where[] = 'l.record_id = ?'; $params[] = $recordId; }
if ($ip !== '') { $where[] = 'l.ip_address LIKE ?'; $params[] = '%' . $ip . '%'; }
if ($keyword !== '') {
    $where[] = '(l.description LIKE ? OR l.request_uri LIKE ? OR l.old_values LIKE ? OR l.new_values LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    $kw = '%' . $keyword . '%';
    array_push($params, $kw, $kw, $kw, $kw, $kw, $kw);
}
$whereSql = implode(' AND ', $where);

$summarySql = 'SELECT
    COUNT(*) total_logs,
    SUM(CASE WHEN l.action LIKE "create%" THEN 1 ELSE 0 END) create_logs,
    SUM(CASE WHEN l.action LIKE "update%" THEN 1 ELSE 0 END) update_logs,
    SUM(CASE WHEN l.action LIKE "delete%" THEN 1 ELSE 0 END) delete_logs,
    SUM(CASE WHEN l.action LIKE "login%" THEN 1 ELSE 0 END) login_logs,
    SUM(CASE WHEN l.action LIKE "access_denied%" OR l.action LIKE "login_failed%" THEN 1 ELSE 0 END) security_logs
    FROM audit_logs l LEFT JOIN users u ON l.user_id=u.id WHERE ' . $whereSql;
$st = $pdo->prepare($summarySql);
$st->execute($params);
$summary = $st->fetch() ?: ['total_logs'=>0,'create_logs'=>0,'update_logs'=>0,'delete_logs'=>0,'login_logs'=>0,'security_logs'=>0];

$sql = 'SELECT l.*,u.name user_name,u.email user_email,u.role user_current_role FROM audit_logs l LEFT JOIN users u ON l.user_id=u.id WHERE ' . $whereSql . ' ORDER BY l.created_at DESC LIMIT ' . $limit;
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

function audit_pretty_json(?string $json): string
{
    if (!$json) return '';
    $decoded = json_decode($json, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return $json;
}
?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title"><h1>Audit Logs</h1><button class="btn" onclick="window.print()">Print</button></div>
<div class="card"><p class="help-text">This tracks login, logout, failed login, access denied, create, update, delete, activate/deactivate, and report-filter actions with user, role snapshot, IP, URL, browser, old values, and new values where available.</p>
<form method="get"><div class="row">
<p><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></p>
<p><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></p>
<p><label>User</label><select name="user_id"><option value="0">All users</option><?php foreach($users as $u): ?><option value="<?= e((string)$u['id']) ?>" <?= selected($userId,$u['id']) ?>><?= e($u['name'].' - '.$u['email']) ?></option><?php endforeach; ?></select></p>
<p><label>Role</label><select name="role"><option value="">All roles</option><?php foreach($roles as $r): ?><option value="<?= e($r) ?>" <?= selected($role,$r) ?>><?= e(role_label($r)) ?></option><?php endforeach; ?></select></p>
<p><label>Action</label><select name="action_filter"><option value="">All actions</option><?php foreach($actions as $a): ?><option value="<?= e($a['action']) ?>" <?= selected($actionFilter,$a['action']) ?>><?= e($a['action']) ?></option><?php endforeach; ?></select></p>
<p><label>Table / Area</label><select name="table_filter"><option value="">All tables/areas</option><?php foreach($tables as $t): ?><option value="<?= e($t['table_name']) ?>" <?= selected($tableFilter,$t['table_name']) ?>><?= e($t['table_name']) ?></option><?php endforeach; ?></select></p>
<p><label>Record ID</label><input type="number" name="record_id" value="<?= e($recordId ? (string)$recordId : '') ?>" placeholder="Optional"></p>
<p><label>IP Address</label><input name="ip" value="<?= e($ip) ?>" placeholder="e.g. 127.0.0.1"></p>
<p><label>Keyword</label><input name="keyword" value="<?= e($keyword) ?>" placeholder="Search description, URL, data, user"></p>
<p><label>Rows</label><select name="limit"><?php foreach([100,250,500,1000,2000] as $l): ?><option value="<?= e((string)$l) ?>" <?= selected($limit,$l) ?>><?= e((string)$l) ?></option><?php endforeach; ?></select></p>
</div><button class="btn secondary">Filter Logs</button> <a class="btn secondary" href="<?= e(url('audit_logs.php')) ?>">Reset</a></form></div>

<div class="grid grid-4">
<div class="stat"><span>Total Logs</span><strong><?= e((string)($summary['total_logs'] ?? 0)) ?></strong></div>
<div class="stat"><span>Create / Update</span><strong><?= e((string)(($summary['create_logs'] ?? 0) + ($summary['update_logs'] ?? 0))) ?></strong></div>
<div class="stat"><span>Deletes</span><strong><?= e((string)($summary['delete_logs'] ?? 0)) ?></strong></div>
<div class="stat"><span>Security Events</span><strong><?= e((string)($summary['security_logs'] ?? 0)) ?></strong></div>
</div>

<div class="card"><h2>Activity Results</h2><div class="table-wrap"><table><thead><tr><th>Date/Time</th><th>User</th><th>Role</th><th>Action</th><th>Area</th><th>Record</th><th>Description</th><th>Request</th><th>IP</th><th>Details</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['created_at']) ?></td><td><?= e(($r['user_name']??'System') . (($r['user_email']??'') ? ' · '.$r['user_email'] : '')) ?></td><td><?= e(role_label($r['role_snapshot'] ?: ($r['user_current_role']??''))) ?></td><td><span class="badge"><?= e($r['action']) ?></span></td><td><?= e($r['table_name']) ?></td><td><?= e((string)$r['record_id']) ?></td><td><?= e($r['description']) ?></td><td><?= e(($r['request_method']??'') . ' ' . ($r['request_uri']??'')) ?></td><td><?= e($r['ip_address']) ?></td><td><?php if(!empty($r['old_values']) || !empty($r['new_values']) || !empty($r['user_agent'])): ?><details><summary>View</summary><?php if(!empty($r['old_values'])): ?><strong>Old values</strong><pre><?= e(audit_pretty_json($r['old_values'])) ?></pre><?php endif; ?><?php if(!empty($r['new_values'])): ?><strong>New values</strong><pre><?= e(audit_pretty_json($r['new_values'])) ?></pre><?php endif; ?><?php if(!empty($r['user_agent'])): ?><strong>Browser</strong><pre><?= e($r['user_agent']) ?></pre><?php endif; ?></details><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="10">No audit logs match these filters.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
