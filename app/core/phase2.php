<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';

function phase2_agent_filter(?int $requestedAgentId = null): array
{
    if (is_branch_scoped_user()) {
        require_branch_assignment();
        return [user_agent_id(), true];
    }
    return [$requestedAgentId && $requestedAgentId > 0 ? $requestedAgentId : null, false];
}

function expected_collections(string $from, string $to, ?int $agentId = null): array
{
    $pdo = db();
    $params = [$from, $to];
    $agentSql = '';
    if ($agentId) {
        $agentSql = ' AND agent_id = ?';
        $params[] = $agentId;
    }
    $ticket = ['cash'=>0,'mobile_money'=>0,'bank'=>0,'total'=>0,'count'=>0];
    $parcel = ['cash'=>0,'mobile_money'=>0,'bank'=>0,'total'=>0,'count'=>0];

    $st = $pdo->prepare('SELECT payment_method, COUNT(*) rows_count, COALESCE(SUM(amount_paid),0) total FROM bookings WHERE DATE(created_at) BETWEEN ? AND ? AND status <> "cancelled"' . $agentSql . ' GROUP BY payment_method');
    $st->execute($params);
    foreach ($st->fetchAll() as $row) {
        $method = $row['payment_method'];
        $ticket[$method] = (float)$row['total'];
        $ticket['total'] += (float)$row['total'];
        $ticket['count'] += (int)$row['rows_count'];
    }

    $st = $pdo->prepare('SELECT payment_method, COUNT(*) rows_count, COALESCE(SUM(amount_paid),0) total FROM parcels WHERE DATE(created_at) BETWEEN ? AND ? AND status <> "cancelled"' . $agentSql . ' GROUP BY payment_method');
    $st->execute($params);
    foreach ($st->fetchAll() as $row) {
        $method = $row['payment_method'];
        $parcel[$method] = (float)$row['total'];
        $parcel['total'] += (float)$row['total'];
        $parcel['count'] += (int)$row['rows_count'];
    }

    $expenseParams = [$from, $to];
    $expenses = 0.0;
    if (!$agentId) {
        $st = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?');
        $st->execute($expenseParams);
        $expenses = (float)$st->fetchColumn();
    }

    return [
        'ticket' => $ticket,
        'parcel' => $parcel,
        'cash_expected' => $ticket['cash'] + $parcel['cash'],
        'mobile_expected' => $ticket['mobile_money'] + $parcel['mobile_money'],
        'bank_expected' => $ticket['bank'] + $parcel['bank'],
        'total_expected' => $ticket['total'] + $parcel['total'],
        'expenses' => $expenses,
    ];
}

function get_agents_for_scope(): array
{
    if (is_branch_scoped_user()) {
        $st = db()->prepare('SELECT id,name,branch,commission_rate FROM agents WHERE id=?');
        $st->execute([user_agent_id()]);
        return $st->fetchAll();
    }
    return db()->query('SELECT id,name,branch,commission_rate FROM agents WHERE status="active" ORDER BY branch,name')->fetchAll();
}

function log_notification(string $channel, ?string $name, ?string $phone, string $message, string $relatedTable, int $recordId, string $status = 'prepared'): int
{
    $user = current_user();
    $stmt = db()->prepare('INSERT INTO notification_logs (channel, recipient_name, recipient_phone, message, related_table, record_id, status, created_by) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([$channel, $name, $phone, $message, $relatedTable, $recordId, $status, $user['id'] ?? null]);
    $id = (int)db()->lastInsertId();
    audit_log('create', 'notification_logs', $id, 'Notification prepared for ' . $relatedTable . ' #' . $recordId, null, audit_record_snapshot('notification_logs', $id));
    return $id;
}

function table_exists(string $table): bool
{
    try {
        $stmt = db()->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) { return false; }
}
