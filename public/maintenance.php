<?php
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/saas.php';
require_once __DIR__ . '/../app/core/csrf.php';
require_roles(['owner','admin','manager']);
require_feature('maintenance');
$pdo = db();
$action = get_value('action','list');
$id = (int)get_value('id','0');
$from = get_value('from', date('Y-m-01'));
$to = get_value('to', date('Y-m-d', strtotime('+30 days')));
$busId = (int)get_value('bus_id','0');
$status = get_value('status','');
$types = ['service','repair','inspection','tyres','oil','other'];
$statuses = ['planned','in_progress','completed','cancelled'];

if (is_post()) {
    verify_csrf();
    $id = (int)post_value('id','0');
    $type = post_value('maintenance_type','service'); if(!in_array($type,$types,true)) $type='service';
    $statusPost = post_value('status','planned'); if(!in_array($statusPost,$statuses,true)) $statusPost='planned';
    $data = [(int)post_value('bus_id'), $type, post_value('service_date'), post_value('odometer_km') ?: null, post_value('vendor'), post_value('description'), post_value('cost') ?: 0, $statusPost, post_value('next_due_date') ?: null, current_user()['id'] ?? null];
    if ($id > 0) {
        $old = audit_record_snapshot('maintenance_records',$id);
        $data[] = $id;
        $pdo->prepare('UPDATE maintenance_records SET bus_id=?,maintenance_type=?,service_date=?,odometer_km=?,vendor=?,description=?,cost=?,status=?,next_due_date=?,created_by=? WHERE id=?')->execute($data);
        audit_log('update','maintenance_records',$id,'Maintenance record updated',$old,audit_record_snapshot('maintenance_records',$id));
        flash('success','Maintenance record updated.');
    } else {
        $pdo->prepare('INSERT INTO maintenance_records (bus_id,maintenance_type,service_date,odometer_km,vendor,description,cost,status,next_due_date,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute($data);
        $newId = (int)$pdo->lastInsertId();
        audit_log('create','maintenance_records',$newId,'Maintenance record created',null,audit_record_snapshot('maintenance_records',$newId));
        flash('success','Maintenance record saved.');
    }
    redirect('maintenance.php');
}

if ($action==='delete' && $id>0 && can_delete_records()) {
    $old = audit_record_snapshot('maintenance_records',$id);
    $pdo->prepare('DELETE FROM maintenance_records WHERE id=?')->execute([$id]);
    audit_log('delete','maintenance_records',$id,'Maintenance record deleted',$old,null);
    flash('success','Maintenance record deleted.');
    redirect('maintenance.php');
}
$row = null;
if ($action==='edit' && $id>0) { $st=$pdo->prepare('SELECT * FROM maintenance_records WHERE id=?'); $st->execute([$id]); $row=$st->fetch(); }
$buses = $pdo->query('SELECT id,bus_number,plate_number,status,next_service_date FROM buses ORDER BY bus_number')->fetchAll();
$where=['m.service_date BETWEEN ? AND ?']; $params=[$from,$to];
if($busId>0){$where[]='m.bus_id=?';$params[]=$busId;} if($status!==''){$where[]='m.status=?';$params[]=$status;}
$st=$pdo->prepare('SELECT m.*,b.bus_number,b.plate_number,u.name created_name FROM maintenance_records m JOIN buses b ON m.bus_id=b.id LEFT JOIN users u ON m.created_by=u.id WHERE '.implode(' AND ',$where).' ORDER BY m.service_date DESC,m.id DESC');
$st->execute($params); $rows=$st->fetchAll();
$dueSoon=$pdo->query('SELECT b.bus_number,b.plate_number,b.next_service_date FROM buses b WHERE b.next_service_date IS NOT NULL AND b.next_service_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY b.next_service_date LIMIT 20')->fetchAll();
$totalCost = array_sum(array_map(fn($r)=>(float)$r['cost'],$rows));
?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title"><h1>Maintenance Reminders</h1><a class="btn" href="<?= e(url('maintenance.php?action=create')) ?>">Add Maintenance</a></div>
<div class="grid grid-3"><div class="stat"><span>Records in filter</span><strong><?= e((string)count($rows)) ?></strong></div><div class="stat"><span>Total cost</span><strong><?= e(money($totalCost)) ?></strong></div><div class="stat"><span>Buses due soon</span><strong><?= e((string)count($dueSoon)) ?></strong></div></div>
<?php if($dueSoon): ?><div class="alert alert-warning"><strong>Service reminders:</strong> <?php foreach(array_slice($dueSoon,0,5) as $d): ?><?= e($d['bus_number'].' '.$d['plate_number'].' due '.app_date($d['next_service_date'])) ?>; <?php endforeach; ?></div><?php endif; ?>
<div class="card"><form method="get"><div class="row"><p><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></p><p><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></p><p><label>Bus</label><select name="bus_id"><option value="0">All buses</option><?php foreach($buses as $b): ?><option value="<?= e((string)$b['id']) ?>" <?= selected($busId,$b['id']) ?>><?= e($b['bus_number'].' '.$b['plate_number']) ?></option><?php endforeach; ?></select></p><p><label>Status</label><select name="status"><option value="">All statuses</option><?php foreach($statuses as $s): ?><option value="<?= e($s) ?>" <?= selected($status,$s) ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option><?php endforeach; ?></select></p></div><button class="btn secondary">Filter</button> <?php if (feature_enabled('exports')): ?><a class="btn success" href="<?= e(url('export.php?type=maintenance&from='.$from.'&to='.$to.'&bus_id='.$busId.'&status='.$status)) ?>">Export CSV</a><?php endif; ?></form></div>
<?php if($action==='create'||$action==='edit'): ?><div class="card"><h2><?= $row?'Edit Maintenance':'Add Maintenance' ?></h2><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string)($row['id']??0)) ?>"><div class="row"><p><label>Bus</label><select name="bus_id" required><?php foreach($buses as $b): ?><option value="<?= e((string)$b['id']) ?>" <?= selected($row['bus_id']??'', $b['id']) ?>><?= e($b['bus_number'].' '.$b['plate_number']) ?></option><?php endforeach; ?></select></p><p><label>Type</label><select name="maintenance_type"><?php foreach($types as $t): ?><option value="<?= e($t) ?>" <?= selected($row['maintenance_type']??'service',$t) ?>><?= e(ucfirst($t)) ?></option><?php endforeach; ?></select></p><p><label>Service Date</label><input type="date" name="service_date" required value="<?= e($row['service_date']??date('Y-m-d')) ?>"></p><p><label>Next Due Date</label><input type="date" name="next_due_date" value="<?= e($row['next_due_date']??'') ?>"></p><p><label>Odometer KM</label><input type="number" name="odometer_km" value="<?= e((string)($row['odometer_km']??'')) ?>"></p><p><label>Vendor / Mechanic</label><input name="vendor" value="<?= e($row['vendor']??'') ?>"></p><p><label>Cost</label><input type="number" step="0.01" name="cost" value="<?= e((string)($row['cost']??0)) ?>"></p><p><label>Status</label><select name="status"><?php foreach($statuses as $s): ?><option value="<?= e($s) ?>" <?= selected($row['status']??'planned',$s) ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option><?php endforeach; ?></select></p></div><p><label>Description</label><textarea name="description" required><?= e($row['description']??'') ?></textarea></p><button class="btn">Save Maintenance</button> <a class="btn secondary" href="<?= e(url('maintenance.php')) ?>">Cancel</a></form></div><?php endif; ?>
<div class="card"><h2>Maintenance Records</h2><div class="table-wrap"><table><thead><tr><th>Date</th><th>Bus</th><th>Type</th><th>Description</th><th>Vendor</th><th>Cost</th><th>Next Due</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e(app_date($r['service_date'])) ?></td><td><?= e($r['bus_number'].' '.$r['plate_number']) ?></td><td><?= e($r['maintenance_type']) ?></td><td><?= e($r['description']) ?></td><td><?= e($r['vendor']) ?></td><td><?= e(money($r['cost'])) ?></td><td><?= e(app_date($r['next_due_date'])) ?></td><td><span class="badge"><?= e($r['status']) ?></span></td><td class="actions"><a class="btn small" href="<?= e(url('maintenance.php?action=edit&id='.$r['id'])) ?>">Edit</a><?php if(can_delete_records()): ?><a class="btn small danger" onclick="return confirm('Delete maintenance record?')" href="<?= e(url('maintenance.php?action=delete&id='.$r['id'])) ?>">Delete</a><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="9">No maintenance records found.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
