<?php
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/saas.php';
require_once __DIR__ . '/../app/core/csrf.php';
require_manager();
require_feature('routes');
$pdo = db();
$action = get_value('action','list');
$id = (int)get_value('id','0');

if (is_post()) {
    verify_csrf();
    $id = (int)post_value('id','0');
    $data = [post_value('origin'), post_value('destination'), post_value('distance_km') ?: null, post_value('standard_fare') ?: 0, post_value('status','active')];
    if ($id > 0) {
        $old = audit_record_snapshot('routes', $id);
        if (($old['status'] ?? '') !== 'active' && post_value('status','active') === 'active') { require_limit_available('routes'); }
        $data[] = $id;
        $pdo->prepare('UPDATE routes SET origin=?,destination=?,distance_km=?,standard_fare=?,status=? WHERE id=?')->execute($data);
        $new = audit_record_snapshot('routes', $id);
        audit_log('update','routes',$id,'Route updated: ' . post_value('origin') . ' → ' . post_value('destination'), $old, $new);
        flash('success','Route updated.');
    } else {
        require_limit_available('routes');
        $pdo->prepare('INSERT INTO routes (origin,destination,distance_km,standard_fare,status) VALUES (?,?,?,?,?)')->execute($data);
        $newId = (int)$pdo->lastInsertId();
        audit_log('create','routes',$newId,'Route added: ' . post_value('origin') . ' → ' . post_value('destination'), null, audit_record_snapshot('routes', $newId));
        flash('success','Route added.');
    }
    redirect('routes.php');
}

if ($action === 'delete' && $id > 0 && can_delete_records()) {
    $old = audit_record_snapshot('routes', $id);
    try {
        $pdo->prepare('DELETE FROM routes WHERE id=?')->execute([$id]);
        audit_log('delete','routes',$id,'Route deleted', $old, null);
        flash('success','Route deleted.');
    } catch(Throwable $e) {
        flash('danger','Cannot delete this route because it is already used in trips.');
    }
    redirect('routes.php');
}

$row = null;
if ($action === 'edit' && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM routes WHERE id=?');
    $st->execute([$id]);
    $row = $st->fetch();
}
$rows = $pdo->query('SELECT * FROM routes ORDER BY origin,destination')->fetchAll();
?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title"><h1>Routes</h1><a class="btn" href="<?= e(url('routes.php?action=create')) ?>">Add Route</a></div>
<?php if($action==='create'||$action==='edit'): ?><div class="card"><h2><?= $row?'Edit Route':'Add Route' ?></h2><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string)($row['id']??0)) ?>"><div class="row"><p><label>Origin</label><input name="origin" required value="<?= e($row['origin']??'') ?>"></p><p><label>Destination</label><input name="destination" required value="<?= e($row['destination']??'') ?>"></p><p><label>Distance KM</label><input type="number" step="0.1" name="distance_km" value="<?= e((string)($row['distance_km']??'')) ?>"></p><p><label>Standard Fare</label><input type="number" step="0.01" name="standard_fare" value="<?= e((string)($row['standard_fare']??'')) ?>"></p><p><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive" <?= (($row['status']??'')==='inactive')?'selected':'' ?>>Inactive</option></select></p></div><button class="btn">Save Route</button> <a class="btn secondary" href="<?= e(url('routes.php')) ?>">Cancel</a></form></div><?php endif; ?>
<div class="card"><h2>Route List</h2><div class="table-wrap"><table><thead><tr><th>Route</th><th>Distance</th><th>Standard Fare</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['origin'].' → '.$r['destination']) ?></td><td><?= e((string)$r['distance_km']) ?> km</td><td><?= e(money($r['standard_fare'])) ?></td><td><span class="badge"><?= e($r['status']) ?></span></td><td class="actions"><a class="btn small" href="<?= e(url('routes.php?action=edit&id='.$r['id'])) ?>">Edit</a><?php if(can_delete_records()): ?><a class="btn small danger" onclick="return confirm('Delete route?')" href="<?= e(url('routes.php?action=delete&id='.$r['id'])) ?>">Delete</a><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="5">No routes yet.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
