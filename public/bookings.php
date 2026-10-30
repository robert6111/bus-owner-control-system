<?php
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/saas.php';
require_once __DIR__ . '/../app/core/csrf.php';
require_roles(['owner','admin','manager','agent','accountant']);
require_feature('bookings');
require_once __DIR__ . '/../app/core/settings.php';

$pdo = db();
$trip_id = (int)get_value('trip_id', '0');
$action = get_value('action', 'list');
$id = (int)get_value('id', '0');
$canWrite = can_manage_bookings();

if (($action === 'create' || $action === 'edit') && !$canWrite) {
    http_response_code(403);
    exit('You can view passenger seats, but your role cannot add or edit them.');
}

if (is_post()) {
    if (!$canWrite) {
        http_response_code(403);
        exit('You do not have permission to save passenger records.');
    }
    verify_csrf();
    $id = (int)post_value('id', '0');
    if ($id > 0) {
        ensure_record_belongs_to_current_agent('bookings', $id);
    }
    $trip_id = (int)post_value('trip_id');
    $seat = post_value('seat_no');
    $agentId = is_branch_scoped_user() ? user_agent_id() : (post_value('agent_id') ? (int)post_value('agent_id') : null);

    $check = $pdo->prepare('SELECT id FROM bookings WHERE trip_id=? AND seat_no=? AND status<>"cancelled" AND id<>? LIMIT 1');
    $check->execute([$trip_id, $seat, $id]);
    if ($check->fetch()) {
        flash('danger', 'This seat is already taken on this trip. Choose another seat.');
        redirect('bookings.php?trip_id=' . $trip_id);
    }

    $data = [$trip_id, $agentId, post_value('passenger_name'), post_value('phone'), $seat, post_value('boarding_point'), post_value('destination_point'), post_value('amount_paid') ?: 0, post_value('payment_method','cash'), post_value('status','booked'), post_value('notes')];
    if ($id > 0) {
        $old = audit_record_snapshot('bookings', $id);
        $data[] = $id;
        $pdo->prepare('UPDATE bookings SET trip_id=?,agent_id=?,passenger_name=?,phone=?,seat_no=?,boarding_point=?,destination_point=?,amount_paid=?,payment_method=?,status=?,notes=? WHERE id=?')->execute($data);
        $new = audit_record_snapshot('bookings', $id);
        audit_log('update', 'bookings', $id, 'Passenger seat updated: seat ' . $seat . ' on trip #' . $trip_id, $old, $new);
        flash('success', 'Booking updated.');
    } else {
        $pdo->prepare('INSERT INTO bookings (trip_id,agent_id,passenger_name,phone,seat_no,boarding_point,destination_point,amount_paid,payment_method,status,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute($data);
        $newId = (int)$pdo->lastInsertId();
        audit_log('create', 'bookings', $newId, 'Passenger seat recorded: seat ' . $seat . ' on trip #' . $trip_id, null, audit_record_snapshot('bookings', $newId));
        flash('success', 'Passenger seat recorded.');
    }
    redirect('bookings.php?trip_id=' . $trip_id);
}

if ($action === 'delete' && $id > 0) {
    require_roles(['owner','admin']);
    $old = audit_record_snapshot('bookings', $id);
    $st = $pdo->prepare('SELECT trip_id FROM bookings WHERE id=?');
    $st->execute([$id]);
    $trip_id = (int)$st->fetchColumn();
    $pdo->prepare('DELETE FROM bookings WHERE id=?')->execute([$id]);
    audit_log('delete', 'bookings', $id, 'Passenger seat deleted', $old, null);
    flash('success', 'Booking deleted.');
    redirect('bookings.php?trip_id=' . $trip_id);
}

$row = null;
if ($action === 'edit' && $id > 0) {
    ensure_record_belongs_to_current_agent('bookings', $id);
    $st = $pdo->prepare('SELECT * FROM bookings WHERE id=?');
    $st->execute([$id]);
    $row = $st->fetch();
    $trip_id = (int)$row['trip_id'];
}

$trips = $pdo->query('SELECT t.id,t.departure_date,t.departure_time,r.origin,r.destination,b.bus_number,b.capacity FROM trips t JOIN routes r ON t.route_id=r.id JOIN buses b ON t.bus_id=b.id ORDER BY t.departure_date DESC,t.departure_time DESC LIMIT 200')->fetchAll();
if (is_branch_scoped_user()) {
    require_branch_assignment();
    $st = $pdo->prepare('SELECT * FROM agents WHERE id=? AND status="active"');
    $st->execute([user_agent_id()]);
    $agents = $st->fetchAll();
} else {
    $agents = $pdo->query('SELECT * FROM agents WHERE status="active" ORDER BY branch,name')->fetchAll();
}

$trip = null; $bookings = []; $capacity = 0;
if ($trip_id > 0) {
    $st = $pdo->prepare('SELECT t.*,r.origin,r.destination,b.bus_number,b.plate_number,b.capacity FROM trips t JOIN routes r ON t.route_id=r.id JOIN buses b ON t.bus_id=b.id WHERE t.id=?');
    $st->execute([$trip_id]);
    $trip = $st->fetch();
    $capacity = (int)($trip['capacity'] ?? 0);

    $where = 'WHERE bk.trip_id=?';
    $params = [$trip_id];
    if (is_branch_scoped_user()) {
        $where .= ' AND bk.agent_id=?';
        $params[] = user_agent_id();
    }
    $st = $pdo->prepare('SELECT bk.*,a.name agent_name,a.branch FROM bookings bk LEFT JOIN agents a ON bk.agent_id=a.id ' . $where . ' ORDER BY CAST(bk.seat_no AS UNSIGNED), bk.seat_no');
    $st->execute($params);
    $bookings = $st->fetchAll();
}
?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title"><h1>Passenger Seat Records</h1></div>
<?php if (is_branch_scoped_user()): ?><div class="alert alert-warning">You can only see and manage passenger records for your assigned branch/agent.</div><?php endif; ?>
<div class="card"><form method="get"><label>Select Trip</label><select name="trip_id" onchange="this.form.submit()"><option value="0">-- Select trip --</option><?php foreach($trips as $t): ?><option value="<?= e((string)$t['id']) ?>" <?= selected($trip_id, $t['id']) ?>><?= e(app_date($t['departure_date']).' '.substr($t['departure_time'],0,5).' · '.$t['origin'].' → '.$t['destination'].' · '.$t['bus_number']) ?></option><?php endforeach; ?></select></form></div>
<?php if($trip): ?>
<div class="card"><h2><?= e($trip['origin'].' → '.$trip['destination'].' · '.$trip['bus_number'].' · '.app_date($trip['departure_date'])) ?></h2><p><strong><?= is_branch_scoped_user() ? 'My branch seats' : 'Seats sold' ?>:</strong> <?= count(array_filter($bookings, fn($b)=>$b['status']!=='cancelled')) ?> / <?= e((string)$capacity) ?> | <strong>Expected full ticket revenue:</strong> <?= e(money($capacity*$trip['fare'])) ?></p></div>
<?php if(($action==='create'||$action==='edit'||$trip_id) && $canWrite): ?>
<div class="card"><h2><?= $row?'Edit Passenger':'Add Passenger Seat' ?></h2><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string)($row['id']??0)) ?>"><input type="hidden" name="trip_id" value="<?= e((string)$trip_id) ?>">
<div class="row"><p><label>Passenger Name</label><input name="passenger_name" required value="<?= e($row['passenger_name']??'') ?>"></p><p><label>Phone</label><input name="phone" value="<?= e($row['phone']??'') ?>"></p><p><label>Seat No</label><input name="seat_no" required value="<?= e($row['seat_no']??'') ?>"></p>
<p><label>Agent/Branch</label><?php if(is_branch_scoped_user()): ?><input value="<?= e(($agents[0]['branch'] ?? '').' - '.($agents[0]['name'] ?? '')) ?>" disabled><input type="hidden" name="agent_id" value="<?= e((string)user_agent_id()) ?>"><?php else: ?><select name="agent_id"><option value="">-- Direct office --</option><?php foreach($agents as $a): ?><option value="<?= e((string)$a['id']) ?>" <?= selected($row['agent_id']??'', $a['id']) ?>><?= e($a['branch'].' - '.$a['name']) ?></option><?php endforeach; ?></select><?php endif; ?></p>
<p><label>Boarding Point</label><input name="boarding_point" value="<?= e($row['boarding_point']??$trip['origin']) ?>"></p><p><label>Destination Point</label><input name="destination_point" value="<?= e($row['destination_point']??$trip['destination']) ?>"></p><p><label>Amount Paid</label><input type="number" step="0.01" name="amount_paid" required value="<?= e((string)($row['amount_paid']??$trip['fare'])) ?>"></p><p><label>Payment Method</label><select name="payment_method"><option value="cash">Cash</option><option value="mobile_money" <?= selected($row['payment_method']??'', 'mobile_money') ?>>Mobile money</option><option value="bank" <?= selected($row['payment_method']??'', 'bank') ?>>Bank</option></select></p><p><label>Status</label><select name="status"><option value="booked">Booked</option><option value="paid" <?= selected($row['status']??'', 'paid') ?>>Paid</option><option value="boarded" <?= selected($row['status']??'', 'boarded') ?>>Boarded</option><option value="cancelled" <?= selected($row['status']??'', 'cancelled') ?>>Cancelled</option></select></p></div><p><label>Notes</label><textarea name="notes"><?= e($row['notes']??'') ?></textarea></p><button class="btn">Save Passenger</button></form></div>
<?php endif; ?>
<div class="card"><h2>Passenger List</h2><div class="table-wrap"><table><thead><tr><th>Seat</th><th>Passenger</th><th>Phone</th><th>Agent</th><th>Paid</th><th>Payment</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($bookings as $b): ?><tr><td><?= e($b['seat_no']) ?></td><td><?= e($b['passenger_name']) ?></td><td><?= e($b['phone']) ?></td><td><?= e(($b['branch']??'Office').' '.($b['agent_name']??'')) ?></td><td><?= e(money($b['amount_paid'])) ?></td><td><?= e($b['payment_method']) ?></td><td><span class="badge"><?= e($b['status']) ?></span></td><td class="actions"><a class="btn small success" href="<?= e(url('ticket_print.php?id='.$b['id'])) ?>" target="_blank">Ticket</a><?php if(!empty($b['phone'])): ?><a class="btn small secondary" target="_blank" href="<?= e(url('notifications.php?action=ticket&id='.$b['id'])) ?>">WhatsApp</a><?php endif; ?><?php if($canWrite): ?><a class="btn small" href="<?= e(url('bookings.php?action=edit&id='.$b['id'])) ?>">Edit</a><?php endif; ?><?php if(can_delete_records()): ?><a class="btn small danger" onclick="return confirm('Delete booking?')" href="<?= e(url('bookings.php?action=delete&id='.$b['id'])) ?>">Delete</a><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$bookings): ?><tr><td colspan="8">No passenger records for this trip.</td></tr><?php endif; ?></tbody></table></div></div>
<?php endif; ?>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
