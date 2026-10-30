<?php
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/saas.php';
require_once __DIR__ . '/../app/core/csrf.php';
require_login();
require_feature('trips');

$pdo = db();
$action = get_value('action', 'list');
$id = (int)get_value('id', '0');

if (($action === 'create' || $action === 'edit') && !can_manage()) {
    http_response_code(403);
    exit('Only owner, admin, or manager can create/edit trips.');
}

if (is_post()) {
    require_roles(['owner', 'admin', 'manager']);
    verify_csrf();
    $id = (int)post_value('id', '0');
    $data = [
        (int)post_value('route_id'),
        (int)post_value('bus_id'),
        post_value('driver_id') ? (int)post_value('driver_id') : null,
        post_value('conductor_name'),
        post_value('departure_date'),
        post_value('departure_time'),
        post_value('fare') ?: 0,
        post_value('status', 'scheduled'),
    ];
    if ($id > 0) {
        $old = audit_record_snapshot('trips', $id);
        $data[] = $id;
        $pdo->prepare('UPDATE trips SET route_id=?,bus_id=?,driver_id=?,conductor_name=?,departure_date=?,departure_time=?,fare=?,status=? WHERE id=?')->execute($data);
        $new = audit_record_snapshot('trips', $id);
        audit_log('update', 'trips', $id, 'Trip updated for ' . post_value('departure_date') . ' ' . post_value('departure_time'), $old, $new);
        flash('success', 'Trip updated.');
    } else {
        require_limit_available('monthly_trips');
        $pdo->prepare('INSERT INTO trips (route_id,bus_id,driver_id,conductor_name,departure_date,departure_time,fare,status) VALUES (?,?,?,?,?,?,?,?)')->execute($data);
        $newId = (int)$pdo->lastInsertId();
        audit_log('create', 'trips', $newId, 'Trip created for ' . post_value('departure_date') . ' ' . post_value('departure_time'), null, audit_record_snapshot('trips', $newId));
        flash('success', 'Trip created.');
    }
    redirect('trips.php');
}

if ($action === 'delete' && $id > 0) {
    require_roles(['owner', 'admin']);
    $old = audit_record_snapshot('trips', $id);
    try {
        $pdo->prepare('DELETE FROM trips WHERE id=?')->execute([$id]);
        audit_log('delete', 'trips', $id, 'Trip deleted', $old, null);
        flash('success', 'Trip deleted.');
    } catch (Throwable $e) {
        flash('danger', 'Cannot delete trip because it has bookings, parcels, or expenses.');
    }
    redirect('trips.php');
}

$row = null;
if ($action === 'edit' && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM trips WHERE id=?');
    $st->execute([$id]);
    $row = $st->fetch();
}

$routes = $pdo->query('SELECT * FROM routes WHERE status="active" ORDER BY origin,destination')->fetchAll();
$buses = $pdo->query('SELECT * FROM buses WHERE status IN ("active","maintenance") ORDER BY bus_number')->fetchAll();
$drivers = $pdo->query('SELECT * FROM drivers WHERE status="active" ORDER BY name')->fetchAll();
$from = get_value('from', date('Y-m-d'));
$to = get_value('to', date('Y-m-d', strtotime('+7 days')));

if (is_branch_scoped_user()) {
    require_branch_assignment();
    $st = $pdo->prepare('SELECT t.*, r.origin,r.destination,b.bus_number,b.plate_number,d.name driver_name,
    (SELECT COUNT(*) FROM bookings WHERE trip_id=t.id AND status<>"cancelled" AND agent_id=?) seats_sold,
    (SELECT COALESCE(SUM(amount_paid),0) FROM bookings WHERE trip_id=t.id AND status<>"cancelled" AND agent_id=?) ticket_sales,
    (SELECT COALESCE(SUM(amount_paid),0) FROM parcels WHERE trip_id=t.id AND status<>"cancelled" AND agent_id=?) parcel_sales,
    0 trip_expenses
    FROM trips t JOIN routes r ON t.route_id=r.id JOIN buses b ON t.bus_id=b.id LEFT JOIN drivers d ON t.driver_id=d.id
    WHERE t.departure_date BETWEEN ? AND ? ORDER BY t.departure_date,t.departure_time');
    $st->execute([user_agent_id(), user_agent_id(), user_agent_id(), $from, $to]);
} else {
    $st = $pdo->prepare('SELECT t.*, r.origin,r.destination,b.bus_number,b.plate_number,d.name driver_name,
    (SELECT COUNT(*) FROM bookings WHERE trip_id=t.id AND status<>"cancelled") seats_sold,
    (SELECT COALESCE(SUM(amount_paid),0) FROM bookings WHERE trip_id=t.id AND status<>"cancelled") ticket_sales,
    (SELECT COALESCE(SUM(amount_paid),0) FROM parcels WHERE trip_id=t.id AND status<>"cancelled") parcel_sales,
    (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE trip_id=t.id) trip_expenses
    FROM trips t JOIN routes r ON t.route_id=r.id JOIN buses b ON t.bus_id=b.id LEFT JOIN drivers d ON t.driver_id=d.id
    WHERE t.departure_date BETWEEN ? AND ? ORDER BY t.departure_date,t.departure_time');
    $st->execute([$from, $to]);
}
$rows = $st->fetchAll();
?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title"><h1>Trips</h1><?php if(can_manage()): ?><a class="btn" href="<?= e(url('trips.php?action=create')) ?>">Create Trip</a><?php endif; ?></div>
<?php if($action==='create'||$action==='edit'): ?>
<div class="card"><h2><?= $row ? 'Edit Trip' : 'Create Trip' ?></h2>
<form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string)($row['id']??0)) ?>">
<div class="row">
<p><label>Route</label><select name="route_id" required><?php foreach($routes as $r): ?><option value="<?= e((string)$r['id']) ?>" <?= selected($row['route_id']??'', $r['id']) ?>><?= e($r['origin'].' → '.$r['destination']) ?></option><?php endforeach; ?></select></p>
<p><label>Bus</label><select name="bus_id" required><?php foreach($buses as $b): ?><option value="<?= e((string)$b['id']) ?>" <?= selected($row['bus_id']??'', $b['id']) ?>><?= e($b['bus_number'].' - '.$b['plate_number'].' ('.$b['capacity'].' seats)') ?></option><?php endforeach; ?></select></p>
<p><label>Driver</label><select name="driver_id"><option value="">-- Select driver --</option><?php foreach($drivers as $d): ?><option value="<?= e((string)$d['id']) ?>" <?= selected($row['driver_id']??'', $d['id']) ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></p>
<p><label>Conductor Name</label><input name="conductor_name" value="<?= e($row['conductor_name']??'') ?>"></p>
<p><label>Departure Date</label><input type="date" name="departure_date" required value="<?= e($row['departure_date']??date('Y-m-d')) ?>"></p>
<p><label>Departure Time</label><input type="time" name="departure_time" required value="<?= e(substr($row['departure_time']??'06:00',0,5)) ?>"></p>
<p><label>Fare</label><input type="number" step="0.01" name="fare" required value="<?= e((string)($row['fare']??0)) ?>"></p>
<p><label>Status</label><select name="status"><option value="scheduled">Scheduled</option><option value="departed" <?= selected($row['status']??'', 'departed') ?>>Departed</option><option value="completed" <?= selected($row['status']??'', 'completed') ?>>Completed</option><option value="cancelled" <?= selected($row['status']??'', 'cancelled') ?>>Cancelled</option></select></p>
</div><button class="btn">Save Trip</button> <a class="btn secondary" href="<?= e(url('trips.php')) ?>">Cancel</a></form></div>
<?php endif; ?>
<div class="card"><form method="get"><div class="row"><p><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></p><p><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></p></div><button class="btn secondary">Filter</button></form></div>
<div class="card"><h2>Trip List</h2><div class="table-wrap"><table><thead><tr><th>Date/Time</th><th>Route</th><th>Bus</th><th>Driver</th><th>Seats</th><th>Revenue</th><th>Expenses</th><th><?= is_branch_scoped_user() ? 'Collections' : 'Profit' ?></th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $r): $profit=$r['ticket_sales']+$r['parcel_sales']-$r['trip_expenses']; ?><tr><td><?= e(app_date($r['departure_date']).' '.substr($r['departure_time'],0,5)) ?></td><td><?= e($r['origin'].' → '.$r['destination']) ?></td><td><?= e($r['bus_number'].' '.$r['plate_number']) ?></td><td><?= e($r['driver_name']??'') ?></td><td><?= e((string)$r['seats_sold']) ?></td><td><?= e(money($r['ticket_sales']+$r['parcel_sales'])) ?></td><td><?= e(money($r['trip_expenses'])) ?></td><td><strong><?= e(money($profit)) ?></strong></td><td><span class="badge"><?= e($r['status']) ?></span></td><td class="actions"><?php if(has_role(['owner','admin','manager','agent','accountant'])): ?><a class="btn small success" href="<?= e(url('bookings.php?trip_id='.$r['id'])) ?>">Seats</a><?php endif; ?><a class="btn small" href="<?= e(url('manifest.php?trip_id='.$r['id'])) ?>">Manifest</a><?php if(can_manage()): ?><a class="btn small" href="<?= e(url('trips.php?action=edit&id='.$r['id'])) ?>">Edit</a><?php endif; ?><?php if(can_delete_records()): ?><a class="btn small danger" onclick="return confirm('Delete trip?')" href="<?= e(url('trips.php?action=delete&id='.$r['id'])) ?>">Delete</a><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="10">No trips found.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
