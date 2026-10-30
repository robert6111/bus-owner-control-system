<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/saas.php';
require_login();
require_feature('reports');
if (!can_view_reports()) {
    http_response_code(403);
    exit('You do not have permission to view reports.');
}

$pdo = db();
$from = get_value('from', date('Y-m-01'));
$to = get_value('to', date('Y-m-d'));
$routeId = (int)get_value('route_id', '0');
$busId = (int)get_value('bus_id', '0');
$agentId = (int)get_value('agent_id', '0');
$tripStatus = get_value('trip_status', '');
$paymentMethod = get_value('payment_method', '');
$expenseCategory = get_value('expense_category', '');
$scope = is_branch_scoped_user();
if ($scope) {
    require_branch_assignment();
    $agentId = (int)user_agent_id();
}

$routes = $pdo->query('SELECT id,origin,destination FROM routes ORDER BY origin,destination')->fetchAll();
$buses = $pdo->query('SELECT id,bus_number,plate_number FROM buses ORDER BY bus_number')->fetchAll();
$agentsList = $scope
    ? (function() use ($pdo) { $st=$pdo->prepare('SELECT id,name,branch FROM agents WHERE id=?'); $st->execute([user_agent_id()]); return $st->fetchAll(); })()
    : $pdo->query('SELECT id,name,branch FROM agents ORDER BY branch,name')->fetchAll();
$expenseCategories = ['fuel','repair','tyres','allowance','office','tax_fee','other'];
$tripStatuses = ['scheduled','departed','completed','cancelled'];
$paymentMethods = ['cash','mobile_money','bank'];

if (!empty($_GET)) {
    audit_log('filter', 'reports', null, 'Business reports filtered', null, [
        'from' => $from,
        'to' => $to,
        'route_id' => $routeId ?: null,
        'bus_id' => $busId ?: null,
        'agent_id' => $agentId ?: null,
        'trip_status' => $tripStatus ?: null,
        'payment_method' => $paymentMethod ?: null,
        'expense_category' => $expenseCategory ?: null,
    ]);
}

function sum_query(string $sql, array $params): float {
    $st = db()->prepare($sql);
    $st->execute($params);
    return (float)($st->fetchColumn() ?: 0);
}
function count_query(string $sql, array $params): int {
    $st = db()->prepare($sql);
    $st->execute($params);
    return (int)($st->fetchColumn() ?: 0);
}
function report_percent(float $part, float $total): string {
    return $total > 0 ? number_format(($part / $total) * 100, 1) . '%' : '0.0%';
}

$tripWhere = ['t.departure_date BETWEEN ? AND ?'];
$tripParams = [$from, $to];
if ($routeId > 0) { $tripWhere[] = 't.route_id = ?'; $tripParams[] = $routeId; }
if ($busId > 0) { $tripWhere[] = 't.bus_id = ?'; $tripParams[] = $busId; }
if ($tripStatus !== '') { $tripWhere[] = 't.status = ?'; $tripParams[] = $tripStatus; }
$tripWhereSql = implode(' AND ', $tripWhere);

$ticketWhere = ['b.status <> "cancelled"', 't.departure_date BETWEEN ? AND ?'];
$ticketParams = [$from, $to];
if ($routeId > 0) { $ticketWhere[] = 't.route_id = ?'; $ticketParams[] = $routeId; }
if ($busId > 0) { $ticketWhere[] = 't.bus_id = ?'; $ticketParams[] = $busId; }
if ($tripStatus !== '') { $ticketWhere[] = 't.status = ?'; $ticketParams[] = $tripStatus; }
if ($agentId > 0) { $ticketWhere[] = 'b.agent_id = ?'; $ticketParams[] = $agentId; }
if ($paymentMethod !== '') { $ticketWhere[] = 'b.payment_method = ?'; $ticketParams[] = $paymentMethod; }
$ticketWhereSql = implode(' AND ', $ticketWhere);

$parcelWhere = ['p.status <> "cancelled"', 'DATE(p.created_at) BETWEEN ? AND ?'];
$parcelParams = [$from, $to];
if ($routeId > 0) { $parcelWhere[] = 't.route_id = ?'; $parcelParams[] = $routeId; }
if ($busId > 0) { $parcelWhere[] = 't.bus_id = ?'; $parcelParams[] = $busId; }
if ($tripStatus !== '') { $parcelWhere[] = 't.status = ?'; $parcelParams[] = $tripStatus; }
if ($agentId > 0) { $parcelWhere[] = 'p.agent_id = ?'; $parcelParams[] = $agentId; }
if ($paymentMethod !== '') { $parcelWhere[] = 'p.payment_method = ?'; $parcelParams[] = $paymentMethod; }
$parcelWhereSql = implode(' AND ', $parcelWhere);

$expenseWhere = ['e.expense_date BETWEEN ? AND ?'];
$expenseParams = [$from, $to];
if ($routeId > 0) { $expenseWhere[] = 't.route_id = ?'; $expenseParams[] = $routeId; }
if ($busId > 0) { $expenseWhere[] = '(e.bus_id = ? OR t.bus_id = ?)'; $expenseParams[] = $busId; $expenseParams[] = $busId; }
if ($tripStatus !== '') { $expenseWhere[] = '(t.status = ? OR t.id IS NULL)'; $expenseParams[] = $tripStatus; }
if ($expenseCategory !== '') { $expenseWhere[] = 'e.category = ?'; $expenseParams[] = $expenseCategory; }
$expenseWhereSql = implode(' AND ', $expenseWhere);

$parcelsEnabled = feature_enabled('parcels');
$isParcelOnly = has_role(['parcel_officer']) && !has_role(['agent']);
$ticket = $isParcelOnly ? 0 : sum_query('SELECT COALESCE(SUM(b.amount_paid),0) FROM bookings b JOIN trips t ON b.trip_id=t.id WHERE ' . $ticketWhereSql, $ticketParams);
$passengerCount = $isParcelOnly ? 0 : count_query('SELECT COUNT(*) FROM bookings b JOIN trips t ON b.trip_id=t.id WHERE ' . $ticketWhereSql, $ticketParams);
$parcel = $parcelsEnabled ? sum_query('SELECT COALESCE(SUM(p.amount_paid),0) FROM parcels p LEFT JOIN trips t ON p.trip_id=t.id WHERE ' . $parcelWhereSql, $parcelParams) : 0;
$parcelCount = $parcelsEnabled ? count_query('SELECT COUNT(*) FROM parcels p LEFT JOIN trips t ON p.trip_id=t.id WHERE ' . $parcelWhereSql, $parcelParams) : 0;
$tripCount = count_query('SELECT COUNT(*) FROM trips t WHERE ' . $tripWhereSql, $tripParams);
$expense = (!$scope && can_manage_finance()) ? sum_query('SELECT COALESCE(SUM(e.amount),0) FROM expenses e LEFT JOIN trips t ON e.trip_id=t.id WHERE ' . $expenseWhereSql, $expenseParams) : 0;
$revenue = $ticket + $parcel;
$profit = $revenue - $expense;
$profitMargin = report_percent($profit, $revenue);
$avgTripProfit = $tripCount > 0 ? $profit / $tripCount : 0;

$ticketSub = ['trip_id=t.id', 'status <> "cancelled"'];
$ticketSubParams = [];
if ($agentId > 0) { $ticketSub[] = 'agent_id = ?'; $ticketSubParams[] = $agentId; }
if ($paymentMethod !== '') { $ticketSub[] = 'payment_method = ?'; $ticketSubParams[] = $paymentMethod; }
$ticketSubSql = implode(' AND ', $ticketSub);
$parcelSub = ['trip_id=t.id', 'status <> "cancelled"'];
$parcelSubParams = [];
if ($agentId > 0) { $parcelSub[] = 'agent_id = ?'; $parcelSubParams[] = $agentId; }
if ($paymentMethod !== '') { $parcelSub[] = 'payment_method = ?'; $parcelSubParams[] = $paymentMethod; }
$parcelSubSql = implode(' AND ', $parcelSub);
$expenseSub = ['trip_id=t.id'];
$expenseSubParams = [];
if ($expenseCategory !== '') { $expenseSub[] = 'category = ?'; $expenseSubParams[] = $expenseCategory; }
$expenseSubSql = implode(' AND ', $expenseSub);

if ($isParcelOnly) {
    $tripSelect = '0 seats_sold, 0 ticket_sales';
    $tripParamsForSelect = [];
} else {
    $tripSelect = '(SELECT COUNT(*) FROM bookings WHERE ' . $ticketSubSql . ') seats_sold, (SELECT COALESCE(SUM(amount_paid),0) FROM bookings WHERE ' . $ticketSubSql . ') ticket_sales';
    $tripParamsForSelect = array_merge($ticketSubParams, $ticketSubParams);
}
$parcelSelect = $parcelsEnabled ? '(SELECT COALESCE(SUM(amount_paid),0) FROM parcels WHERE ' . $parcelSubSql . ') parcel_sales' : '0 parcel_sales';
$expenseSelect = (!$scope && can_manage_finance()) ? '(SELECT COALESCE(SUM(amount),0) FROM expenses WHERE ' . $expenseSubSql . ') trip_expenses' : '0 trip_expenses';
$tripSql = 'SELECT t.id,t.departure_date,t.departure_time,t.status,t.fare,r.id route_id,r.origin,r.destination,b.id bus_id,b.bus_number,b.plate_number,b.capacity,
    ' . $tripSelect . ',
    ' . $parcelSelect . ',
    ' . $expenseSelect . '
    FROM trips t JOIN routes r ON t.route_id=r.id JOIN buses b ON t.bus_id=b.id
    WHERE ' . $tripWhereSql . ' ORDER BY t.departure_date,t.departure_time LIMIT 1000';
$tripReportParams = array_merge($tripParamsForSelect, $parcelsEnabled ? $parcelSubParams : [], (!$scope && can_manage_finance()) ? $expenseSubParams : [], $tripParams);
$st = $pdo->prepare($tripSql);
$st->execute($tripReportParams);
$trips = $st->fetchAll();

$routeTotals = [];
$busTotals = [];
$dailyTotals = [];
foreach ($trips as $t) {
    $tripRevenue = (float)$t['ticket_sales'] + (float)$t['parcel_sales'];
    $tripProfit = $tripRevenue - (float)$t['trip_expenses'];
    $routeKey = $t['route_id'];
    if (!isset($routeTotals[$routeKey])) {
        $routeTotals[$routeKey] = ['route' => $t['origin'].' → '.$t['destination'], 'trips' => 0, 'seats' => 0, 'ticket' => 0, 'parcel' => 0, 'expenses' => 0, 'profit' => 0];
    }
    $routeTotals[$routeKey]['trips']++;
    $routeTotals[$routeKey]['seats'] += (int)$t['seats_sold'];
    $routeTotals[$routeKey]['ticket'] += (float)$t['ticket_sales'];
    $routeTotals[$routeKey]['parcel'] += (float)$t['parcel_sales'];
    $routeTotals[$routeKey]['expenses'] += (float)$t['trip_expenses'];
    $routeTotals[$routeKey]['profit'] += $tripProfit;

    $busKey = $t['bus_id'];
    if (!isset($busTotals[$busKey])) {
        $busTotals[$busKey] = ['bus' => $t['bus_number'].' '.$t['plate_number'], 'trips' => 0, 'seats' => 0, 'ticket' => 0, 'parcel' => 0, 'expenses' => 0, 'profit' => 0];
    }
    $busTotals[$busKey]['trips']++;
    $busTotals[$busKey]['seats'] += (int)$t['seats_sold'];
    $busTotals[$busKey]['ticket'] += (float)$t['ticket_sales'];
    $busTotals[$busKey]['parcel'] += (float)$t['parcel_sales'];
    $busTotals[$busKey]['expenses'] += (float)$t['trip_expenses'];
    $busTotals[$busKey]['profit'] += $tripProfit;

    $day = $t['departure_date'];
    if (!isset($dailyTotals[$day])) {
        $dailyTotals[$day] = ['date' => $day, 'trips' => 0, 'ticket' => 0, 'parcel' => 0, 'expenses' => 0, 'profit' => 0];
    }
    $dailyTotals[$day]['trips']++;
    $dailyTotals[$day]['ticket'] += (float)$t['ticket_sales'];
    $dailyTotals[$day]['parcel'] += (float)$t['parcel_sales'];
    $dailyTotals[$day]['expenses'] += (float)$t['trip_expenses'];
    $dailyTotals[$day]['profit'] += $tripProfit;
}
usort($routeTotals, fn($a,$b) => $b['profit'] <=> $a['profit']);
usort($busTotals, fn($a,$b) => $b['profit'] <=> $a['profit']);
ksort($dailyTotals);

$agentTotals = [];
if (!$isParcelOnly) {
    $st = $pdo->prepare('SELECT COALESCE(a.id,0) agent_id,COALESCE(a.branch,"Office") branch,COALESCE(a.name,"Direct office") agent_name,COUNT(b.id) ticket_count,COALESCE(SUM(b.amount_paid),0) ticket_sales
        FROM bookings b LEFT JOIN agents a ON b.agent_id=a.id JOIN trips t ON b.trip_id=t.id WHERE ' . $ticketWhereSql . ' GROUP BY a.id,a.branch,a.name');
    $st->execute($ticketParams);
    foreach ($st->fetchAll() as $a) {
        $key = (int)$a['agent_id'];
        $agentTotals[$key] = ['branch'=>$a['branch'], 'agent_name'=>$a['agent_name'], 'ticket_count'=>(int)$a['ticket_count'], 'ticket_sales'=>(float)$a['ticket_sales'], 'parcel_count'=>0, 'parcel_sales'=>0];
    }
}
if ($parcelsEnabled) {
    $st = $pdo->prepare('SELECT COALESCE(a.id,0) agent_id,COALESCE(a.branch,"Office") branch,COALESCE(a.name,"Direct office") agent_name,COUNT(p.id) parcel_count,COALESCE(SUM(p.amount_paid),0) parcel_sales
        FROM parcels p LEFT JOIN agents a ON p.agent_id=a.id LEFT JOIN trips t ON p.trip_id=t.id WHERE ' . $parcelWhereSql . ' GROUP BY a.id,a.branch,a.name');
    $st->execute($parcelParams);
    foreach ($st->fetchAll() as $a) {
        $key = (int)$a['agent_id'];
        if (!isset($agentTotals[$key])) {
            $agentTotals[$key] = ['branch'=>$a['branch'], 'agent_name'=>$a['agent_name'], 'ticket_count'=>0, 'ticket_sales'=>0, 'parcel_count'=>0, 'parcel_sales'=>0];
        }
        $agentTotals[$key]['parcel_count'] = (int)$a['parcel_count'];
        $agentTotals[$key]['parcel_sales'] = (float)$a['parcel_sales'];
    }
}
usort($agentTotals, fn($a,$b) => (($b['ticket_sales']+$b['parcel_sales']) <=> ($a['ticket_sales']+$a['parcel_sales'])));

$expenseCats = [];
if (!$scope && can_manage_finance()) {
    $st = $pdo->prepare('SELECT e.category, COUNT(e.id) entries, SUM(e.amount) total FROM expenses e LEFT JOIN trips t ON e.trip_id=t.id WHERE ' . $expenseWhereSql . ' GROUP BY e.category ORDER BY total DESC');
    $st->execute($expenseParams);
    $expenseCats = $st->fetchAll();
}

$paymentTotals = [];
if (!$isParcelOnly) {
    $st = $pdo->prepare('SELECT b.payment_method, COUNT(*) count_rows, COALESCE(SUM(b.amount_paid),0) total FROM bookings b JOIN trips t ON b.trip_id=t.id WHERE ' . $ticketWhereSql . ' GROUP BY b.payment_method');
    $st->execute($ticketParams);
    foreach ($st->fetchAll() as $p) {
        $paymentTotals[$p['payment_method']] = ['method'=>$p['payment_method'], 'ticket_count'=>(int)$p['count_rows'], 'ticket_total'=>(float)$p['total'], 'parcel_count'=>0, 'parcel_total'=>0];
    }
}
if ($parcelsEnabled) {
    $st = $pdo->prepare('SELECT p.payment_method, COUNT(*) count_rows, COALESCE(SUM(p.amount_paid),0) total FROM parcels p LEFT JOIN trips t ON p.trip_id=t.id WHERE ' . $parcelWhereSql . ' GROUP BY p.payment_method');
    $st->execute($parcelParams);
    foreach ($st->fetchAll() as $p) {
        if (!isset($paymentTotals[$p['payment_method']])) {
            $paymentTotals[$p['payment_method']] = ['method'=>$p['payment_method'], 'ticket_count'=>0, 'ticket_total'=>0, 'parcel_count'=>0, 'parcel_total'=>0];
        }
        $paymentTotals[$p['payment_method']]['parcel_count'] = (int)$p['count_rows'];
        $paymentTotals[$p['payment_method']]['parcel_total'] = (float)$p['total'];
    }
}

$parcelStatuses = [];
$parcelStatusWhere = $parcelWhere;
$parcelStatusParams = $parcelParams;
if ($parcelsEnabled) {
    $st = $pdo->prepare('SELECT p.status, COUNT(*) count_rows, COALESCE(SUM(p.amount_paid),0) total FROM parcels p LEFT JOIN trips t ON p.trip_id=t.id WHERE ' . $parcelWhereSql . ' GROUP BY p.status ORDER BY count_rows DESC');
    $st->execute($parcelParams);
    $parcelStatuses = $st->fetchAll();
}
?>
<?php
function report_find_label(array $rows, int $id, callable $formatter, string $fallback): string {
    if ($id <= 0) return $fallback;
    foreach ($rows as $row) {
        if ((int)$row['id'] === $id) return $formatter($row);
    }
    return $fallback;
}
$reportTitle = $scope ? 'Branch Operations Report' : 'Business Performance Report';
$generatedAt = date('d M Y H:i');
$currentUser = current_user() ?: ['name' => 'System', 'role' => ''];
$companyProfile = company_profile();
$routeLabel = report_find_label($routes, $routeId, fn($r) => $r['origin'].' → '.$r['destination'], 'All routes');
$busLabel = report_find_label($buses, $busId, fn($b) => $b['bus_number'].' '.$b['plate_number'], 'All buses');
$agentLabel = report_find_label($agentsList, $agentId, fn($a) => $a['branch'].' - '.$a['name'], $scope ? 'Assigned branch only' : 'All agents/branches');
$filterSummary = [
    'Period' => app_date($from) . ' to ' . app_date($to),
    'Route' => $routeLabel,
    'Bus' => $busLabel,
    'Agent/Branch' => $agentLabel,
    'Trip Status' => $tripStatus !== '' ? ucfirst($tripStatus) : 'All statuses',
    'Payment' => $paymentMethod !== '' ? str_replace('_',' ',ucfirst($paymentMethod)) : 'All payment methods',
    'Expense Category' => $expenseCategory !== '' ? str_replace('_',' ',ucfirst($expenseCategory)) : 'All categories',
];
