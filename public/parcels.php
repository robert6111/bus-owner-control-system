<?php
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/saas.php';
require_once __DIR__ . '/../app/core/csrf.php';
require_roles(['owner','admin','manager','agent','parcel_officer','accountant']);
require_feature('parcels');
require_once __DIR__ . '/../app/core/settings.php';

$pdo = db();
$action = get_value('action','list');
$id = (int)get_value('id','0');
$canWrite = can_manage_parcels();

function tracking_code(): string { return 'PCL-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)),0,6)); }

if (($action === 'create' || $action === 'edit') && !$canWrite) {
    http_response_code(403);
    exit('You can view parcel records, but your role cannot add or edit them.');
}

if (is_post()) {
    if (!$canWrite) {
        http_response_code(403);
        exit('You do not have permission to save parcel records.');
    }
    verify_csrf();
    $id = (int)post_value('id','0');
    if ($id > 0) {
        ensure_record_belongs_to_current_agent('parcels', $id);
    }
    $code = post_value('tracking_code') ?: tracking_code();
    $agentId = is_branch_scoped_user() ? user_agent_id() : (post_value('agent_id') ? (int)post_value('agent_id') : null);
    $data = [$code, post_value('trip_id') ? (int)post_value('trip_id') : null, $agentId, post_value('sender_name'), post_value('sender_phone'), post_value('receiver_name'), post_value('receiver_phone'), post_value('description'), post_value('from_branch'), post_value('to_branch'), post_value('amount_paid') ?: 0, post_value('payment_method','cash'), post_value('status','received')];
    if ($id > 0) {
        $old = audit_record_snapshot('parcels', $id);
        $data[] = $id;
        $pdo->prepare('UPDATE parcels SET tracking_code=?,trip_id=?,agent_id=?,sender_name=?,sender_phone=?,receiver_name=?,receiver_phone=?,description=?,from_branch=?,to_branch=?,amount_paid=?,payment_method=?,status=? WHERE id=?')->execute($data);
        $new = audit_record_snapshot('parcels', $id);
        audit_log('update', 'parcels', $id, 'Parcel updated: ' . $code, $old, $new);
        flash('success','Parcel updated.');
    } else {
        $pdo->prepare('INSERT INTO parcels (tracking_code,trip_id,agent_id,sender_name,sender_phone,receiver_name,receiver_phone,description,from_branch,to_branch,amount_paid,payment_method,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($data);
        $newId = (int)$pdo->lastInsertId();
        audit_log('create', 'parcels', $newId, 'Parcel recorded: ' . $code, null, audit_record_snapshot('parcels', $newId));
        flash('success','Parcel recorded with tracking code '.$code);
    }
    redirect('parcels.php');
}

if ($action === 'delete' && $id > 0) {
    require_roles(['owner','admin']);
    $old = audit_record_snapshot('parcels', $id);
    $pdo->prepare('DELETE FROM parcels WHERE id=?')->execute([$id]);
    audit_log('delete', 'parcels', $id, 'Parcel deleted', $old, null);
    flash('success','Parcel deleted.');
    redirect('parcels.php');
}

$row = null;
if ($action === 'edit' && $id > 0) {
    ensure_record_belongs_to_current_agent('parcels', $id);
    $st = $pdo->prepare('SELECT * FROM parcels WHERE id=?');
    $st->execute([$id]);
    $row = $st->fetch();
}

$trips = $pdo->query('SELECT t.id,t.departure_date,t.departure_time,r.origin,r.destination,b.bus_number FROM trips t JOIN routes r ON t.route_id=r.id JOIN buses b ON t.bus_id=b.id ORDER BY t.departure_date DESC,t.departure_time DESC LIMIT 200')->fetchAll();
if (is_branch_scoped_user()) {
    require_branch_assignment();
    $st = $pdo->prepare('SELECT * FROM agents WHERE id=? AND status="active"');
    $st->execute([user_agent_id()]);
    $agents = $st->fetchAll();
} else {
    $agents = $pdo->query('SELECT * FROM agents WHERE status="active" ORDER BY branch,name')->fetchAll();
}

$from = get_value('from', date('Y-m-01'));
$to = get_value('to', date('Y-m-d'));
$where = 'WHERE DATE(p.created_at) BETWEEN ? AND ?';
$params = [$from, $to];
if (is_branch_scoped_user()) {
    $where .= ' AND p.agent_id=?';
    $params[] = user_agent_id();
}
$st = $pdo->prepare('SELECT p.*,a.name agent_name,a.branch,t.departure_date,r.origin,r.destination FROM parcels p LEFT JOIN agents a ON p.agent_id=a.id LEFT JOIN trips t ON p.trip_id=t.id LEFT JOIN routes r ON t.route_id=r.id ' . $where . ' ORDER BY p.id DESC');
$st->execute($params);
$rows = $st->fetchAll();
?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title"><h1>Parcel / Cargo Tracking</h1><?php if($canWrite): ?><a class="btn" href="<?= e(url('parcels.php?action=create')) ?>">Record Parcel</a><?php endif; ?></div>
<?php if (is_branch_scoped_user()): ?><div class="alert alert-warning">You can only see and manage parcels attached to your assigned branch/agent.</div><?php endif; ?>
<?php if($action==='create'||$action==='edit'): ?>
<div class="card"><h2><?= $row?'Edit Parcel':'Record Parcel' ?></h2><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string)($row['id']??0)) ?>"><input type="hidden" name="tracking_code" value="<?= e($row['tracking_code']??'') ?>">
<div class="row"><p><label>Trip Optional</label><select name="trip_id"><option value="">-- No trip assigned yet --</option><?php foreach($trips as $t): ?><option value="<?= e((string)$t['id']) ?>" <?= selected($row['trip_id']??'', $t['id']) ?>><?= e(app_date($t['departure_date']).' '.$t['origin'].' → '.$t['destination'].' '.$t['bus_number']) ?></option><?php endforeach; ?></select></p>
<p><label>Agent/Branch</label><?php if(is_branch_scoped_user()): ?><input value="<?= e(($agents[0]['branch'] ?? '').' - '.($agents[0]['name'] ?? '')) ?>" disabled><input type="hidden" name="agent_id" value="<?= e((string)user_agent_id()) ?>"><?php else: ?><select name="agent_id"><option value="">-- Direct office --</option><?php foreach($agents as $a): ?><option value="<?= e((string)$a['id']) ?>" <?= selected($row['agent_id']??'', $a['id']) ?>><?= e($a['branch'].' - '.$a['name']) ?></option><?php endforeach; ?></select><?php endif; ?></p>
<p><label>Sender Name</label><input name="sender_name" required value="<?= e($row['sender_name']??'') ?>"></p><p><label>Sender Phone</label><input name="sender_phone" value="<?= e($row['sender_phone']??'') ?>"></p><p><label>Receiver Name</label><input name="receiver_name" required value="<?= e($row['receiver_name']??'') ?>"></p><p><label>Receiver Phone</label><input name="receiver_phone" value="<?= e($row['receiver_phone']??'') ?>"></p><p><label>From Branch</label><input name="from_branch" value="<?= e($row['from_branch']??'') ?>"></p><p><label>To Branch</label><input name="to_branch" value="<?= e($row['to_branch']??'') ?>"></p><p><label>Amount Paid</label><input type="number" step="0.01" name="amount_paid" required value="<?= e((string)($row['amount_paid']??0)) ?>"></p><p><label>Payment Method</label><select name="payment_method"><option value="cash">Cash</option><option value="mobile_money" <?= selected($row['payment_method']??'', 'mobile_money') ?>>Mobile money</option><option value="bank" <?= selected($row['payment_method']??'', 'bank') ?>>Bank</option></select></p><p><label>Status</label><select name="status"><option value="received">Received</option><option value="loaded" <?= selected($row['status']??'', 'loaded') ?>>Loaded</option><option value="in_transit" <?= selected($row['status']??'', 'in_transit') ?>>In transit</option><option value="delivered" <?= selected($row['status']??'', 'delivered') ?>>Delivered</option><option value="cancelled" <?= selected($row['status']??'', 'cancelled') ?>>Cancelled</option></select></p></div><p><label>Description</label><textarea name="description" required><?= e($row['description']??'') ?></textarea></p><button class="btn">Save Parcel</button> <a class="btn secondary" href="<?= e(url('parcels.php')) ?>">Cancel</a></form></div>
<?php endif; ?>
<div class="card"><form method="get"><div class="row"><p><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></p><p><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></p></div><button class="btn secondary">Filter</button></form></div>
<div class="card"><h2>Parcel Records</h2><div class="table-wrap"><table><thead><tr><th>Tracking</th><th>Route/Trip</th><th>Sender</th><th>Receiver</th><th>Branch</th><th>Paid</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><strong><?= e($r['tracking_code']) ?></strong></td><td><?= e(($r['origin']??$r['from_branch']).' → '.($r['destination']??$r['to_branch'])) ?></td><td><?= e($r['sender_name'].' '.$r['sender_phone']) ?></td><td><?= e($r['receiver_name'].' '.$r['receiver_phone']) ?></td><td><?= e(($r['branch']??'Office').' '.($r['agent_name']??'')) ?></td><td><?= e(money($r['amount_paid'])) ?></td><td><span class="badge"><?= e($r['status']) ?></span></td><td class="actions"><a class="btn small success" href="<?= e(url('parcel_receipt.php?id='.$r['id'])) ?>" target="_blank">Receipt</a><?php if(!empty($r['receiver_phone'])): ?><a class="btn small secondary" target="_blank" href="<?= e(url('notifications.php?action=parcel&id='.$r['id'])) ?>">WhatsApp</a><?php endif; ?><?php if($canWrite): ?><a class="btn small" href="<?= e(url('parcels.php?action=edit&id='.$r['id'])) ?>">Edit</a><?php endif; ?><?php if(can_delete_records()): ?><a class="btn small danger" onclick="return confirm('Delete parcel?')" href="<?= e(url('parcels.php?action=delete&id='.$r['id'])) ?>">Delete</a><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="8">No parcels found.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
