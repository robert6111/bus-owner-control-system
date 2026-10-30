<?php
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/saas.php';
require_once __DIR__ . '/../app/core/csrf.php';
require_roles(['owner','admin','manager']);
require_feature('fuel');
$pdo = db();
$action = get_value('action','list');
$id = (int)get_value('id','0');
$from = get_value('from', date('Y-m-01'));
$to = get_value('to', date('Y-m-d'));
$busId = (int)get_value('bus_id','0');

if (is_post()) {
    verify_csrf();
    $id=(int)post_value('id','0');
    $litres=(float)(post_value('litres') ?: 0);
    $price=(float)(post_value('price_per_litre') ?: 0);
    $total=(float)(post_value('total_amount') ?: ($litres*$price));
    $data=[(int)post_value('bus_id'), post_value('trip_id') ? (int)post_value('trip_id') : null, post_value('fuel_date'), $litres, $price, $total, post_value('odometer_km') ?: null, post_value('station'), post_value('notes'), current_user()['id'] ?? null];
    if($id>0){$old=audit_record_snapshot('fuel_logs',$id);$data[]=$id;$pdo->prepare('UPDATE fuel_logs SET bus_id=?,trip_id=?,fuel_date=?,litres=?,price_per_litre=?,total_amount=?,odometer_km=?,station=?,notes=?,created_by=? WHERE id=?')->execute($data); audit_log('update','fuel_logs',$id,'Fuel log updated',$old,audit_record_snapshot('fuel_logs',$id)); flash('success','Fuel log updated.');}
    else {$pdo->prepare('INSERT INTO fuel_logs (bus_id,trip_id,fuel_date,litres,price_per_litre,total_amount,odometer_km,station,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute($data); $newId=(int)$pdo->lastInsertId(); audit_log('create','fuel_logs',$newId,'Fuel log created',null,audit_record_snapshot('fuel_logs',$newId)); flash('success','Fuel log saved.');}
    redirect('fuel.php');
}
if($action==='delete' && $id>0 && can_delete_records()){ $old=audit_record_snapshot('fuel_logs',$id); $pdo->prepare('DELETE FROM fuel_logs WHERE id=?')->execute([$id]); audit_log('delete','fuel_logs',$id,'Fuel log deleted',$old,null); flash('success','Fuel log deleted.'); redirect('fuel.php'); }
$row=null; if($action==='edit' && $id>0){$st=$pdo->prepare('SELECT * FROM fuel_logs WHERE id=?');$st->execute([$id]);$row=$st->fetch();}
$buses=$pdo->query('SELECT id,bus_number,plate_number FROM buses ORDER BY bus_number')->fetchAll();
$trips=$pdo->query('SELECT t.id,t.departure_date,t.departure_time,r.origin,r.destination,b.bus_number FROM trips t JOIN routes r ON t.route_id=r.id JOIN buses b ON t.bus_id=b.id ORDER BY t.departure_date DESC LIMIT 300')->fetchAll();
$where=['f.fuel_date BETWEEN ? AND ?'];$params=[$from,$to]; if($busId>0){$where[]='f.bus_id=?';$params[]=$busId;}
$st=$pdo->prepare('SELECT f.*,b.bus_number,b.plate_number,t.departure_date,r.origin,r.destination,u.name created_name FROM fuel_logs f JOIN buses b ON f.bus_id=b.id LEFT JOIN trips t ON f.trip_id=t.id LEFT JOIN routes r ON t.route_id=r.id LEFT JOIN users u ON f.created_by=u.id WHERE '.implode(' AND ',$where).' ORDER BY f.fuel_date DESC,f.id DESC');
$st->execute($params);$rows=$st->fetchAll();
$totalLitres=array_sum(array_map(fn($r)=>(float)$r['litres'],$rows)); $totalAmount=array_sum(array_map(fn($r)=>(float)$r['total_amount'],$rows)); $avgPrice=$totalLitres>0?$totalAmount/$totalLitres:0;
?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title"><h1>Fuel Consumption Tracking</h1><a class="btn" href="<?= e(url('fuel.php?action=create')) ?>">Add Fuel</a></div>
<div class="grid grid-3"><div class="stat"><span>Total litres</span><strong><?= e(number_format($totalLitres,2)) ?> L</strong></div><div class="stat"><span>Total fuel cost</span><strong><?= e(money($totalAmount)) ?></strong></div><div class="stat"><span>Average price/litre</span><strong><?= e(money($avgPrice)) ?></strong></div></div>
<div class="card"><form method="get"><div class="row"><p><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></p><p><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></p><p><label>Bus</label><select name="bus_id"><option value="0">All buses</option><?php foreach($buses as $b): ?><option value="<?= e((string)$b['id']) ?>" <?= selected($busId,$b['id']) ?>><?= e($b['bus_number'].' '.$b['plate_number']) ?></option><?php endforeach; ?></select></p></div><button class="btn secondary">Filter</button> <?php if (feature_enabled('exports')): ?><a class="btn success" href="<?= e(url('export.php?type=fuel&from='.$from.'&to='.$to.'&bus_id='.$busId)) ?>">Export CSV</a><?php endif; ?></form></div>
<?php if($action==='create'||$action==='edit'): ?><div class="card"><h2><?= $row?'Edit Fuel Log':'Add Fuel Log' ?></h2><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string)($row['id']??0)) ?>"><div class="row"><p><label>Bus</label><select name="bus_id" required><?php foreach($buses as $b): ?><option value="<?= e((string)$b['id']) ?>" <?= selected($row['bus_id']??'', $b['id']) ?>><?= e($b['bus_number'].' '.$b['plate_number']) ?></option><?php endforeach; ?></select></p><p><label>Trip Optional</label><select name="trip_id"><option value="">-- Not linked --</option><?php foreach($trips as $t): ?><option value="<?= e((string)$t['id']) ?>" <?= selected($row['trip_id']??'', $t['id']) ?>><?= e(app_date($t['departure_date']).' '.$t['origin'].' → '.$t['destination'].' '.$t['bus_number']) ?></option><?php endforeach; ?></select></p><p><label>Fuel Date</label><input type="date" name="fuel_date" required value="<?= e($row['fuel_date']??date('Y-m-d')) ?>"></p><p><label>Litres</label><input type="number" step="0.01" name="litres" value="<?= e((string)($row['litres']??0)) ?>"></p><p><label>Price Per Litre</label><input type="number" step="0.01" name="price_per_litre" value="<?= e((string)($row['price_per_litre']??0)) ?>"></p><p><label>Total Amount Override</label><input type="number" step="0.01" name="total_amount" value="<?= e((string)($row['total_amount']??0)) ?>"></p><p><label>Odometer KM</label><input type="number" name="odometer_km" value="<?= e((string)($row['odometer_km']??'')) ?>"></p><p><label>Station</label><input name="station" value="<?= e($row['station']??'') ?>"></p></div><p><label>Notes</label><textarea name="notes"><?= e($row['notes']??'') ?></textarea></p><button class="btn">Save Fuel Log</button> <a class="btn secondary" href="<?= e(url('fuel.php')) ?>">Cancel</a></form></div><?php endif; ?>
<div class="card"><h2>Fuel Logs</h2><div class="table-wrap"><table><thead><tr><th>Date</th><th>Bus</th><th>Trip</th><th>Litres</th><th>Price/L</th><th>Total</th><th>Odometer</th><th>Station</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e(app_date($r['fuel_date'])) ?></td><td><?= e($r['bus_number'].' '.$r['plate_number']) ?></td><td><?= e(($r['origin']??'').' → '.($r['destination']??'')) ?></td><td><?= e(number_format((float)$r['litres'],2)) ?></td><td><?= e(money($r['price_per_litre'])) ?></td><td><?= e(money($r['total_amount'])) ?></td><td><?= e((string)$r['odometer_km']) ?></td><td><?= e($r['station']) ?></td><td class="actions"><a class="btn small" href="<?= e(url('fuel.php?action=edit&id='.$r['id'])) ?>">Edit</a><?php if(can_delete_records()): ?><a class="btn small danger" onclick="return confirm('Delete fuel log?')" href="<?= e(url('fuel.php?action=delete&id='.$r['id'])) ?>">Delete</a><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="9">No fuel logs found.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
