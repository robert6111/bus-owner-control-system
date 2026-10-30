<?php
require_once __DIR__ . '/../app/core/phase2.php';
require_once __DIR__ . '/../app/core/saas.php';
require_login();
require_feature('dashboard');

$today = date('Y-m-d');
$pdo = db();
$user = current_user();
$scopeTitle = is_branch_scoped_user() ? 'My Branch Dashboard' : 'Owner Dashboard';

function scalar_query(string $sql, array $params = []) {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() ?: 0;
}

$todayTrips = scalar_query('SELECT COUNT(*) FROM trips WHERE departure_date = ?', [$today]);
$activeBuses = scalar_query('SELECT COUNT(*) FROM buses WHERE status = "active"');

$bookingWhere = '';
$bookingParams = [$today];
$parcelWhere = '';
$parcelParams = [$today];

if (is_branch_scoped_user()) {
    require_branch_assignment();
    $bookingWhere = ' AND b.agent_id = ?';
    $bookingParams[] = user_agent_id();
    $parcelWhere = ' AND p.agent_id = ?';
    $parcelParams[] = user_agent_id();
}

$bookingRevenue = has_role(['parcel_officer']) && !has_role(['agent']) ? 0 : scalar_query('SELECT COALESCE(SUM(b.amount_paid),0) FROM bookings b JOIN trips t ON b.trip_id=t.id WHERE t.departure_date=? AND b.status <> "cancelled"' . $bookingWhere, $bookingParams);
$parcelRevenue = feature_enabled('parcels') ? scalar_query('SELECT COALESCE(SUM(p.amount_paid),0) FROM parcels p LEFT JOIN trips t ON p.trip_id=t.id WHERE DATE(p.created_at)=? AND p.status <> "cancelled"' . $parcelWhere, $parcelParams) : 0;
$expenses = can_manage_finance() && !is_branch_scoped_user() ? scalar_query('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date=?', [$today]) : 0;
$profit = $bookingRevenue + $parcelRevenue - $expenses;

if (is_branch_scoped_user()) {
    $tripsSql = 'SELECT t.*, r.origin, r.destination, b.bus_number, b.plate_number,
        COALESCE((SELECT SUM(amount_paid) FROM bookings WHERE trip_id=t.id AND status<>"cancelled" AND agent_id=?),0) as ticket_sales,
        COALESCE((SELECT SUM(amount_paid) FROM parcels WHERE trip_id=t.id AND status<>"cancelled" AND agent_id=?),0) as parcel_sales,
        0 as trip_expenses
        FROM trips t
        JOIN routes r ON t.route_id=r.id
        JOIN buses b ON t.bus_id=b.id
        WHERE t.departure_date=?
        ORDER BY t.departure_time ASC
        LIMIT 10';
    $trips = $pdo->prepare($tripsSql);
    $trips->execute([user_agent_id(), user_agent_id(), $today]);
} else {
    $tripsSql = 'SELECT t.*, r.origin, r.destination, b.bus_number, b.plate_number,
        COALESCE((SELECT SUM(amount_paid) FROM bookings WHERE trip_id=t.id AND status<>"cancelled"),0) as ticket_sales,
        COALESCE((SELECT SUM(amount_paid) FROM parcels WHERE trip_id=t.id AND status<>"cancelled"),0) as parcel_sales,
        COALESCE((SELECT SUM(amount) FROM expenses WHERE trip_id=t.id),0) as trip_expenses
        FROM trips t
        JOIN routes r ON t.route_id=r.id
        JOIN buses b ON t.bus_id=b.id
        WHERE t.departure_date=?
        ORDER BY t.departure_time ASC
        LIMIT 10';
    $trips = $pdo->prepare($tripsSql);
    $trips->execute([$today]);
}
$todayTripRows = $trips->fetchAll();

$license = latest_license();
$licenseState = license_state_label($license);
$maintenanceDue = (feature_enabled('maintenance') && has_role(['owner','admin','manager'])) ? scalar_query('SELECT COUNT(*) FROM buses WHERE next_service_date IS NOT NULL AND next_service_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)') : 0;
$fuelMonth = (feature_enabled('fuel') && has_role(['owner','admin','manager']) && table_exists('fuel_logs')) ? scalar_query('SELECT COALESCE(SUM(total_amount),0) FROM fuel_logs WHERE fuel_date BETWEEN ? AND ?', [date('Y-m-01'), $today]) : 0;
$cashExpectedToday = (feature_enabled('cash_closing') && can_manage_finance()) ? expected_collections($today, $today, is_branch_scoped_user() ? user_agent_id() : null) : ['total_expected'=>0];
$cashClosedToday = (feature_enabled('cash_closing') && can_manage_finance() && table_exists('cash_closings')) ? scalar_query('SELECT COALESCE(SUM(cash_submitted+mobile_money_submitted+bank_submitted),0) FROM cash_closings WHERE closing_date=?' . (is_branch_scoped_user() ? ' AND agent_id='.(int)user_agent_id() : ''), [$today]) : 0;
$chartDays = [];
if (!is_branch_scoped_user()) {
    for ($i=6; $i>=0; $i--) {
        $d = date('Y-m-d', strtotime('-'.$i.' days'));
        $rev = scalar_query('SELECT COALESCE(SUM(amount_paid),0) FROM bookings WHERE DATE(created_at)=? AND status<>"cancelled"', [$d]) + (feature_enabled('parcels') ? scalar_query('SELECT COALESCE(SUM(amount_paid),0) FROM parcels WHERE DATE(created_at)=? AND status<>"cancelled"', [$d]) : 0);
        $chartDays[] = ['date'=>$d,'label'=>date('d M', strtotime($d)), 'revenue'=>$rev];
    }
}
$chartMax = $chartDays ? max(1, max(array_column($chartDays,'revenue'))) : 1;

?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title">
    <h1><?= e($scopeTitle) ?></h1>
    <?php if (can_manage() && feature_enabled('trips')): ?><a class="btn" href="<?= e(url('trips.php?action=create')) ?>">Create Trip</a><?php endif; ?>
</div>

<?php if (is_branch_scoped_user()): ?>
<div class="alert alert-warning">You are viewing only records attached to your assigned branch/agent account.</div>
<?php endif; ?>

<div class="grid grid-4">
    <div class="stat"><span>Today Trips</span><strong><?= e((string)$todayTrips) ?></strong></div>
    <div class="stat"><span>Active Buses</span><strong><?= e((string)$activeBuses) ?></strong></div>
    <div class="stat"><span><?= is_branch_scoped_user() ? 'My Today Collections' : 'Today Revenue' ?></span><strong><?= e(money($bookingRevenue + $parcelRevenue)) ?></strong></div>
    <div class="stat"><span><?= is_branch_scoped_user() ? 'My Net Collections' : 'Today Profit' ?></span><strong><?= e(money($profit)) ?></strong></div>
</div>


<?php if (has_role(['owner','admin'])): $pkg = package_tier(); ?>
<div class="card">
    <h2>Package usage summary</h2>
    <div class="grid grid-4">
        <?php foreach(['users'=>'Users','branches'=>'Branches','buses'=>'Buses','monthly_trips'=>'Trips this month'] as $key=>$label): $st=package_limit_status($key); ?>
            <div class="stat"><span><?= e($label) ?></span><strong><?= e(number_format($st['usage'])) ?> / <?= e(limit_label($st['limit'])) ?></strong><small><?= e($st['unlimited'] ? 'Unlimited package' : $st['percent'].'% used') ?></small></div>
        <?php endforeach; ?>
    </div>
    <p><a class="btn small" href="<?= e(url('packages.php')) ?>">Manage package</a></p>
</div>
<?php endif; ?>

<div class="grid grid-4">
    <div class="stat"><span>Today closing expected</span><strong><?= e(money($cashExpectedToday['total_expected'] ?? 0)) ?></strong><small>From tickets + parcels</small></div>
    <div class="stat"><span>Today submitted</span><strong><?= e(money($cashClosedToday)) ?></strong><small>Daily cash closing</small></div>
    <div class="stat"><span>Maintenance due soon</span><strong><?= e((string)$maintenanceDue) ?></strong><small>Next 30 days</small></div>
    <div class="stat"><span>Fuel this month</span><strong><?= e(money($fuelMonth)) ?></strong><small>Fuel logs</small></div>
</div>


<?php if (!is_branch_scoped_user() && $chartDays): ?>
<div class="card">
    <h2>7-Day Revenue Trend</h2>
    <div class="mini-chart" aria-label="Revenue chart">
        <?php foreach($chartDays as $d): $height = max(8, ((float)$d['revenue'] / $chartMax) * 140); ?>
            <div class="mini-chart-bar" style="height: <?= e((string)$height) ?>px"><span><?= e(money($d['revenue'])) ?></span></div>
        <?php endforeach; ?>
    </div>
    <div class="mini-chart-labels"><?php foreach($chartDays as $d): ?><span><?= e($d['label']) ?></span><?php endforeach; ?></div>
</div>
<?php endif; ?>

<div class="card">
    <h2><?= is_branch_scoped_user() ? 'My Branch Trip Collections' : "Today's Trip Profit Snapshot" ?></h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Time</th><th>Route</th><th>Bus</th><th>Ticket Sales</th><th>Parcel Sales</th><th>Expenses</th><th>Profit/Collections</th></tr></thead>
            <tbody>
            <?php foreach ($todayTripRows as $row): $rowProfit = $row['ticket_sales'] + $row['parcel_sales'] - $row['trip_expenses']; ?>
                <tr>
                    <td><?= e(substr($row['departure_time'],0,5)) ?></td>
                    <td><?= e($row['origin'] . ' → ' . $row['destination']) ?></td>
                    <td><?= e($row['bus_number'] . ' (' . $row['plate_number'] . ')') ?></td>
                    <td><?= e(money($row['ticket_sales'])) ?></td>
                    <td><?= e(money($row['parcel_sales'])) ?></td>
                    <td><?= e(money($row['trip_expenses'])) ?></td>
                    <td><strong><?= e(money($rowProfit)) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$todayTripRows): ?><tr><td colspan="7">No trips scheduled today.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Access level</h2>
    <p>You are logged in as <strong><?= e(role_label($user['role'])) ?></strong>. Role access protects owner reports, finance, staff accounts, branch records, and delete actions.</p><p>Licence status: <span class="license-chip <?= e($licenseState['class']) ?>"><?= e($licenseState['label']) ?></span></p>
</div>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
