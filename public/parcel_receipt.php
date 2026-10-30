<?php
require_once __DIR__ . '/../app/core/phase2.php';
require_once __DIR__ . '/../app/core/saas.php';
require_login();
require_feature('parcels');
$id = (int)get_value('id','0');
if ($id <= 0) { exit('Missing parcel id.'); }
if (is_branch_scoped_user()) { ensure_record_belongs_to_current_agent('parcels',$id); }
$st = db()->prepare('SELECT p.*,a.name agent_name,a.branch,t.departure_date,t.departure_time,r.origin,r.destination,b.bus_number,b.plate_number FROM parcels p LEFT JOIN agents a ON p.agent_id=a.id LEFT JOIN trips t ON p.trip_id=t.id LEFT JOIN routes r ON t.route_id=r.id LEFT JOIN buses b ON t.bus_id=b.id WHERE p.id=?');
$st->execute([$id]);
$row = $st->fetch();
if (!$row) { exit('Parcel not found.'); }
audit_log('print','parcels',$id,'Parcel receipt print/download opened',null,['tracking_code'=>$row['tracking_code']]);
$company = company_profile();
$route = ($row['origin'] ?? $row['from_branch']) . ' → ' . ($row['destination'] ?? $row['to_branch']);
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($row['tracking_code']) ?></title><link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>"></head><body class="receipt-page">
<div class="no-print" style="max-width:820px;margin:0 auto 12px;display:flex;gap:8px;justify-content:flex-end"><button class="btn" onclick="window.print()">Download / Save as PDF</button><a class="btn secondary" href="<?= e(url('parcels.php')) ?>">Back</a></div>
<div class="receipt-document"><div class="receipt-header"><div><h1><?= e($company['name']) ?></h1><p><?= e($company['address']) ?></p><p><?= e($company['phone']) ?> · <?= e($company['email']) ?></p></div><div class="text-right"><h1>Parcel Receipt</h1><p><strong><?= e($row['tracking_code']) ?></strong></p><span class="watermark-status"><?= e(strtoupper($row['status'])) ?></span></div></div><div class="receipt-box">
<div class="receipt-meta"><div><span>Sender</span><strong><?= e($row['sender_name']) ?></strong><small><?= e($row['sender_phone']) ?></small></div><div><span>Receiver</span><strong><?= e($row['receiver_name']) ?></strong><small><?= e($row['receiver_phone']) ?></small></div><div><span>Route</span><strong><?= e($route) ?></strong><small><?= e($row['departure_date'] ? app_date($row['departure_date']).' '.substr($row['departure_time'],0,5) : 'Not assigned to trip') ?></small></div><div><span>Amount / Payment</span><strong><?= e(money($row['amount_paid'])) ?></strong><small><?= e(str_replace('_',' ',ucfirst($row['payment_method']))) ?></small></div></div>
<table class="report-table"><tr><th>Description</th><td colspan="3"><?= e($row['description']) ?></td></tr><tr><th>Agent/Branch</th><td><?= e(($row['branch']??'Office').' '.($row['agent_name']??'')) ?></td><th>Printed On</th><td><?= e(date('d M Y H:i')) ?></td></tr><tr><th>Bus</th><td><?= e(($row['bus_number']??'').' '.($row['plate_number']??'')) ?></td><th>Status</th><td><?= e($row['status']) ?></td></tr></table>
<p class="report-note"><?= e($company['footer']) ?></p><div class="receipt-signatures"><div>Sender signature</div><div>Parcel officer</div><div>Receiver signature</div></div></div></div></body></html>
