<?php
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/saas.php';
require_once __DIR__ . '/../app/core/csrf.php';
require_manager();
require_feature('drivers');
$pdo = db();
$action = get_value('action','list');
$id = (int)get_value('id','0');

if (is_post()) {
    verify_csrf();
    $id = (int)post_value('id','0');
    $data = [post_value('name'), post_value('phone'), post_value('license_number'), post_value('license_expiry') ?: null, post_value('status','active'), post_value('notes')];
    if ($id > 0) {
        $old = audit_record_snapshot('drivers', $id);
        $data[] = $id;
        $pdo->prepare('UPDATE drivers SET name=?,phone=?,license_number=?,license_expiry=?,status=?,notes=? WHERE id=?')->execute($data);
        $new = audit_record_snapshot('drivers', $id);
        audit_log('update','drivers',$id,'Driver updated: ' . post_value('name'), $old, $new);
        flash('success','Driver updated.');
    } else {
        $pdo->prepare('INSERT INTO drivers (name,phone,license_number,license_expiry,status,notes) VALUES (?,?,?,?,?,?)')->execute($data);
        $newId = (int)$pdo->lastInsertId();
        audit_log('create','drivers',$newId,'Driver added: ' . post_value('name'), null, audit_record_snapshot('drivers', $newId));
        flash('success','Driver added.');
    }
    redirect('drivers.php');
}

if ($action === 'delete' && $id > 0 && can_delete_records()) {
    $old = audit_record_snapshot('drivers', $id);
    try {
        $pdo->prepare('DELETE FROM drivers WHERE id=?')->execute([$id]);
        audit_log('delete','drivers',$id,'Driver deleted', $old, null);
        flash('success','Driver deleted.');
    } catch(Throwable $e) {
        flash('danger','Cannot delete driver because it is used in trips.');
    }
    redirect('drivers.php');
}

$row = null;
if ($action === 'edit' && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM drivers WHERE id=?');
    $st->execute([$id]);
    $row = $st->fetch();
}
$rows = $pdo->query('SELECT * FROM drivers ORDER BY name')->fetchAll();
?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title"><h1>Drivers</h1><a class="btn" href="<?= e(url('drivers.php?action=create')) ?>">Add Driver</a></div>
<?php if($action==='create'||$action==='edit'): ?><div class="card"><h2><?= $row?'Edit Driver':'Add Driver' ?></h2><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string)($row['id']??0)) ?>"><div class="row"><p><label>Name</label><input name="name" required value="<?= e($row['name']??'') ?>"></p><p><label>Phone</label><input name="phone" value="<?= e($row['phone']??'') ?>"></p><p><label>Licence Number</label><input name="license_number" value="<?= e($row['license_number']??'') ?>"></p><p><label>Licence Expiry</label><input type="date" name="license_expiry" value="<?= e($row['license_expiry']??'') ?>"></p><p><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive" <?= (($row['status']??'')==='inactive')?'selected':'' ?>>Inactive</option></select></p></div><p><label>Notes</label><textarea name="notes"><?= e($row['notes']??'') ?></textarea></p><button class="btn">Save Driver</button> <a class="btn secondary" href="<?= e(url('drivers.php')) ?>">Cancel</a></form></div><?php endif; ?>
<div class="card"><h2>Driver List</h2><div class="table-wrap"><table><thead><tr><th>Name</th><th>Phone</th><th>Licence</th><th>Expiry</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['phone']) ?></td><td><?= e($r['license_number']) ?></td><td><?= e($r['license_expiry']??'') ?></td><td><span class="badge"><?= e($r['status']) ?></span></td><td class="actions"><a class="btn small" href="<?= e(url('drivers.php?action=edit&id='.$r['id'])) ?>">Edit</a><?php if(can_delete_records()): ?><a class="btn small danger" onclick="return confirm('Delete driver?')" href="<?= e(url('drivers.php?action=delete&id='.$r['id'])) ?>">Delete</a><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="6">No drivers yet.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
