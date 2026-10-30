<?php
require_once __DIR__ . '/../app/core/phase2.php';
require_once __DIR__ . '/../app/core/saas.php';
require_once __DIR__ . '/../app/core/csrf.php';
require_roles(['owner','admin','manager','agent','parcel_officer']);
require_feature('notifications');
$pdo = db();
$action = get_value('action','list');
$from = get_value('from', date('Y-m-01'));
$to = get_value('to', date('Y-m-d'));

if ($action === 'ticket') {
    $id = (int)get_value('id','0');
    if (is_branch_scoped_user()) { ensure_record_belongs_to_current_agent('bookings',$id); }
    $st=$pdo->prepare('SELECT bk.*,t.departure_date,t.departure_time,r.origin,r.destination FROM bookings bk JOIN trips t ON bk.trip_id=t.id JOIN routes r ON t.route_id=r.id WHERE bk.id=?');
    $st->execute([$id]); $b=$st->fetch(); if(!$b){exit('Booking not found.');}
    $message='Hello '.$b['passenger_name'].', your ticket '.ticket_number($id).' for '.$b['origin'].' to '.$b['destination'].' on '.app_date($b['departure_date']).' at '.substr($b['departure_time'],0,5).' seat '.$b['seat_no'].' is confirmed.';
    log_notification('whatsapp',$b['passenger_name'],$b['phone'],$message,'bookings',$id,'opened');
    header('Location: ' . whatsapp_url($b['phone'],$message)); exit;
}
if ($action === 'parcel') {
    $id = (int)get_value('id','0');
    if (is_branch_scoped_user()) { ensure_record_belongs_to_current_agent('parcels',$id); }
    $st=$pdo->prepare('SELECT * FROM parcels WHERE id=?');$st->execute([$id]);$p=$st->fetch(); if(!$p){exit('Parcel not found.');}
    $message='Hello '.$p['receiver_name'].', parcel '.$p['tracking_code'].' from '.$p['from_branch'].' to '.$p['to_branch'].' is recorded. Status: '.$p['status'].'.';
    log_notification('whatsapp',$p['receiver_name'],$p['receiver_phone'],$message,'parcels',$id,'opened');
    header('Location: ' . whatsapp_url($p['receiver_phone'],$message)); exit;
}

$where='WHERE DATE(n.created_at) BETWEEN ? AND ?'; $params=[$from,$to];
$st=$pdo->prepare('SELECT n.*,u.name user_name FROM notification_logs n LEFT JOIN users u ON n.created_by=u.id '.$where.' ORDER BY n.created_at DESC LIMIT 500');
$st->execute($params); $logs=$st->fetchAll();
$ticketWhere='WHERE DATE(b.created_at) BETWEEN ? AND ? AND b.status<>"cancelled"'; $ticketParams=[$from,$to];
$parcelWhere='WHERE DATE(p.created_at) BETWEEN ? AND ? AND p.status<>"cancelled"'; $parcelParams=[$from,$to];
if(is_branch_scoped_user()){require_branch_assignment();$ticketWhere.=' AND b.agent_id=?';$ticketParams[]=user_agent_id();$parcelWhere.=' AND p.agent_id=?';$parcelParams[]=user_agent_id();}
$st=$pdo->prepare('SELECT b.id,b.passenger_name,b.phone,b.seat_no,b.created_at,t.departure_date,t.departure_time,r.origin,r.destination FROM bookings b JOIN trips t ON b.trip_id=t.id JOIN routes r ON t.route_id=r.id '.$ticketWhere.' ORDER BY b.id DESC LIMIT 50');$st->execute($ticketParams);$bookings=$st->fetchAll();
$st=$pdo->prepare('SELECT p.id,p.tracking_code,p.receiver_name,p.receiver_phone,p.from_branch,p.to_branch,p.status,p.created_at FROM parcels p '.$parcelWhere.' ORDER BY p.id DESC LIMIT 50');$st->execute($parcelParams);$parcels=$st->fetchAll();
?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title"><h1>WhatsApp / SMS Notification Center</h1></div>
<div class="alert alert-warning">This Phase 2 version prepares WhatsApp messages using secure links. It does not require paid SMS APIs yet. Every opened notification is stored in audit and notification logs.</div>
<div class="card"><form method="get"><div class="row"><p><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></p><p><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></p></div><button class="btn secondary">Filter</button></form></div>
<div class="grid grid-2"><div class="card"><h2>Recent Passenger Messages</h2><div class="table-wrap"><table><thead><tr><th>Passenger</th><th>Route</th><th>Trip</th><th>Action</th></tr></thead><tbody><?php foreach($bookings as $b): ?><tr><td><?= e($b['passenger_name']) ?><br><small><?= e($b['phone']) ?></small></td><td><?= e($b['origin'].' → '.$b['destination']) ?></td><td><?= e(app_date($b['departure_date']).' '.substr($b['departure_time'],0,5).' · Seat '.$b['seat_no']) ?></td><td><a class="btn small success" target="_blank" href="<?= e(url('notifications.php?action=ticket&id='.$b['id'])) ?>">Open WhatsApp</a></td></tr><?php endforeach; ?><?php if(!$bookings): ?><tr><td colspan="4">No passenger records found.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="card"><h2>Recent Parcel Messages</h2><div class="table-wrap"><table><thead><tr><th>Receiver</th><th>Parcel</th><th>Route</th><th>Action</th></tr></thead><tbody><?php foreach($parcels as $p): ?><tr><td><?= e($p['receiver_name']) ?><br><small><?= e($p['receiver_phone']) ?></small></td><td><?= e($p['tracking_code']) ?><br><small><?= e($p['status']) ?></small></td><td><?= e($p['from_branch'].' → '.$p['to_branch']) ?></td><td><a class="btn small success" target="_blank" href="<?= e(url('notifications.php?action=parcel&id='.$p['id'])) ?>">Open WhatsApp</a></td></tr><?php endforeach; ?><?php if(!$parcels): ?><tr><td colspan="4">No parcel records found.</td></tr><?php endif; ?></tbody></table></div></div></div>
<div class="card"><h2>Notification Logs</h2><div class="table-wrap"><table><thead><tr><th>Date</th><th>Channel</th><th>Recipient</th><th>Related</th><th>Status</th><th>User</th><th>Message</th></tr></thead><tbody><?php foreach($logs as $l): ?><tr><td><?= e($l['created_at']) ?></td><td><?= e($l['channel']) ?></td><td><?= e($l['recipient_name'].' '.$l['recipient_phone']) ?></td><td><?= e($l['related_table'].' #'.$l['record_id']) ?></td><td><span class="badge"><?= e($l['status']) ?></span></td><td><?= e($l['user_name']??'') ?></td><td><?= e($l['message']) ?></td></tr><?php endforeach; ?><?php if(!$logs): ?><tr><td colspan="7">No notification logs yet.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
