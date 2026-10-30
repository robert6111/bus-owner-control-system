<?php
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/saas.php';
require_login();
require_feature('trips');

$trip_id = (int)get_value('trip_id','0');
$pdo = db();
$st = $pdo->prepare('SELECT t.*,r.origin,r.destination,b.bus_number,b.plate_number,d.name driver_name FROM trips t JOIN routes r ON t.route_id=r.id JOIN buses b ON t.bus_id=b.id LEFT JOIN drivers d ON t.driver_id=d.id WHERE t.id=?');
$st->execute([$trip_id]);
$trip = $st->fetch();
if (!$trip) { http_response_code(404); exit('Trip not found.'); }

$bookingWhere = 'WHERE trip_id=? AND status<>"cancelled"';
$bookingParams = [$trip_id];
$parcelWhere = 'WHERE trip_id=? AND status<>"cancelled"';
$parcelParams = [$trip_id];
if (is_branch_scoped_user()) {
    require_branch_assignment();
    $bookingWhere .= ' AND agent_id=?';
    $bookingParams[] = user_agent_id();
    $parcelWhere .= ' AND agent_id=?';
    $parcelParams[] = user_agent_id();
}
$st = $pdo->prepare('SELECT * FROM bookings ' . $bookingWhere . ' ORDER BY CAST(seat_no AS UNSIGNED), seat_no');
$st->execute($bookingParams);
$passengers = $st->fetchAll();
$parcels = [];
if (feature_enabled('parcels')) {
    $st = $pdo->prepare('SELECT * FROM parcels ' . $parcelWhere . ' ORDER BY id');
    $st->execute($parcelParams);
    $parcels = $st->fetchAll();
}
?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title"><h1><?= is_branch_scoped_user() ? 'My Branch Trip Manifest' : 'Trip Manifest' ?></h1><button class="btn" onclick="window.print()">Print</button></div>
<?php if(is_branch_scoped_user()): ?><div class="alert alert-warning">You are viewing only passengers/parcels attached to your assigned branch/agent.</div><?php endif; ?>
<div class="card"><h2><?= e($trip['origin'].' → '.$trip['destination']) ?></h2><p><strong>Date:</strong> <?= e(app_date($trip['departure_date'])) ?> <?= e(substr($trip['departure_time'],0,5)) ?> | <strong>Bus:</strong> <?= e($trip['bus_number'].' '.$trip['plate_number']) ?> | <strong>Driver:</strong> <?= e($trip['driver_name']??'') ?> | <strong>Conductor:</strong> <?= e($trip['conductor_name']) ?></p></div>
<div class="card"><h2>Passenger Manifest</h2><div class="table-wrap"><table><thead><tr><th>Seat</th><th>Name</th><th>Phone</th><th>Boarding</th><th>Destination</th><th>Status</th></tr></thead><tbody><?php foreach($passengers as $p): ?><tr><td><?= e($p['seat_no']) ?></td><td><?= e($p['passenger_name']) ?></td><td><?= e($p['phone']) ?></td><td><?= e($p['boarding_point']) ?></td><td><?= e($p['destination_point']) ?></td><td><?= e($p['status']) ?></td></tr><?php endforeach; ?><?php if(!$passengers): ?><tr><td colspan="6">No passengers.</td></tr><?php endif; ?></tbody></table></div></div>
<?php if (feature_enabled('parcels')): ?><div class="card"><h2>Parcels on this Trip</h2><div class="table-wrap"><table><thead><tr><th>Tracking</th><th>Sender</th><th>Receiver</th><th>Description</th><th>Paid</th><th>Status</th></tr></thead><tbody><?php foreach($parcels as $p): ?><tr><td><?= e($p['tracking_code']) ?></td><td><?= e($p['sender_name'].' '.$p['sender_phone']) ?></td><td><?= e($p['receiver_name'].' '.$p['receiver_phone']) ?></td><td><?= e($p['description']) ?></td><td><?= e(money($p['amount_paid'])) ?></td><td><?= e($p['status']) ?></td></tr><?php endforeach; ?><?php if(!$parcels): ?><tr><td colspan="6">No parcels.</td></tr><?php endif; ?></tbody></table></div></div><?php endif; ?>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
