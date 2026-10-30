<?php
require_once __DIR__ . '/../app/core/phase2.php';
require_once __DIR__ . '/../app/core/saas.php';
require_login();
require_feature('bookings');
$id = (int)get_value('id','0');
if ($id <= 0) { exit('Missing ticket id.'); }
if (is_branch_scoped_user()) { ensure_record_belongs_to_current_agent('bookings',$id); }
$st = db()->prepare('SELECT bk.*,a.name agent_name,a.branch,t.departure_date,t.departure_time,t.fare,t.conductor_name,r.origin,r.destination,b.bus_number,b.plate_number,b.capacity,d.name driver_name FROM bookings bk JOIN trips t ON bk.trip_id=t.id JOIN routes r ON t.route_id=r.id JOIN buses b ON t.bus_id=b.id LEFT JOIN drivers d ON t.driver_id=d.id LEFT JOIN agents a ON bk.agent_id=a.id WHERE bk.id=?');
$st->execute([$id]);
$row = $st->fetch();
if (!$row) { exit('Ticket not found.'); }
audit_log('print','bookings',$id,'Ticket print/download opened',null,['ticket_no'=>ticket_number($id)]);
$company = company_profile();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e(ticket_number($id)) ?></title><link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>"></head><body class="receipt-page">
<div class="no-print" style="max-width:820px;margin:0 auto 12px;display:flex;gap:8px;justify-content:flex-end"><button class="btn" onclick="window.print()">Download / Save as PDF</button><a class="btn secondary" href="<?= e(url('bookings.php?trip_id='.$row['trip_id'])) ?>">Back</a></div>
<div class="receipt-document"><div class="receipt-header"><div><h1><?= e($company['name']) ?></h1><p><?= e($company['address']) ?></p><p><?= e($company['phone']) ?> · <?= e($company['email']) ?></p></div><div class="text-right"><h1>Passenger Ticket</h1><p><strong><?= e(ticket_number($id)) ?></strong></p><span class="watermark-status"><?= e(strtoupper($row['status'])) ?></span></div></div><div class="receipt-box">
<div class="receipt-meta"><div><span>Passenger</span><strong><?= e($row['passenger_name']) ?></strong><small><?= e($row['phone']) ?></small></div><div><span>Route</span><strong><?= e($row['origin'].' → '.$row['destination']) ?></strong><small><?= e(app_date($row['departure_date']).' '.substr($row['departure_time'],0,5)) ?></small></div><div><span>Bus</span><strong><?= e($row['bus_number'].' '.$row['plate_number']) ?></strong><small>Driver: <?= e($row['driver_name']??'') ?></small></div><div><span>Seat / Fare</span><strong>Seat <?= e($row['seat_no']) ?> · <?= e(money($row['amount_paid'])) ?></strong><small><?= e(str_replace('_',' ',ucfirst($row['payment_method']))) ?></small></div></div>
<table class="report-table"><tr><th>Boarding Point</th><td><?= e($row['boarding_point']) ?></td><th>Destination Point</th><td><?= e($row['destination_point']) ?></td></tr><tr><th>Agent/Branch</th><td><?= e(($row['branch']??'Office').' '.($row['agent_name']??'')) ?></td><th>Printed On</th><td><?= e(date('d M Y H:i')) ?></td></tr><tr><th>Notes</th><td colspan="3"><?= e($row['notes']??'') ?></td></tr></table>
<p class="report-note"><?= e($company['footer']) ?></p><div class="receipt-signatures"><div>Passenger signature</div><div>Ticket officer</div><div>Conductor check</div></div></div></div></body></html>
