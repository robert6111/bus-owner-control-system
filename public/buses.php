<?php
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/saas.php';
require_once __DIR__ . '/../app/core/csrf.php';
require_manager();
require_feature('buses');

$pdo = db();
$action = get_value('action', 'list');
$id = (int)get_value('id', '0');

if (is_post()) {
    verify_csrf();
    $id = (int)post_value('id', '0');
    $data = [
        post_value('bus_number'), post_value('plate_number'), (int)post_value('capacity', '0'),
        post_value('model'), post_value('status', 'active'), post_value('latra_license_expiry') ?: null,
        post_value('insurance_expiry') ?: null, post_value('next_service_date') ?: null, post_value('notes')
    ];
    if ($id > 0) {
        $old = audit_record_snapshot('buses', $id);
        if (($old['status'] ?? '') === 'inactive' && post_value('status','active') !== 'inactive') { require_limit_available('buses'); }
        $data[] = $id;
        $stmt = $pdo->prepare('UPDATE buses SET bus_number=?, plate_number=?, capacity=?, model=?, status=?, latra_license_expiry=?, insurance_expiry=?, next_service_date=?, notes=? WHERE id=?');
        $stmt->execute($data);
        $new = audit_record_snapshot('buses', $id);
        audit_log('update','buses',$id,'Bus updated: ' . post_value('bus_number') . ' / ' . post_value('plate_number'), $old, $new);
        flash('success', 'Bus updated.');
    } else {
        require_limit_available('buses');
        $stmt = $pdo->prepare('INSERT INTO buses (bus_number, plate_number, capacity, model, status, latra_license_expiry, insurance_expiry, next_service_date, notes) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute($data);
        $newId = (int)$pdo->lastInsertId();
        audit_log('create','buses',$newId,'Bus added: ' . post_value('bus_number') . ' / ' . post_value('plate_number'), null, audit_record_snapshot('buses', $newId));
        flash('success', 'Bus added.');
    }
    redirect('buses.php');
}

if ($action === 'delete' && $id > 0 && can_delete_records()) {
    $old = audit_record_snapshot('buses', $id);
    $stmt = $pdo->prepare('DELETE FROM buses WHERE id=?');
    try { $stmt->execute([$id]); audit_log('delete','buses',$id,'Bus deleted', $old, null); flash('success', 'Bus deleted.'); } catch (Throwable $e) { flash('danger', 'Cannot delete this bus because it is already used in trips or expenses.'); }
    redirect('buses.php');
}

$row = null;
if (($action === 'edit') && $id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM buses WHERE id=?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
}
$rows = $pdo->query('SELECT * FROM buses ORDER BY id DESC')->fetchAll();
?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title"><h1>Buses / Fleet</h1><a class="btn" href="<?= e(url('buses.php?action=create')) ?>">Add Bus</a></div>
<?php if ($action === 'create' || $action === 'edit'): ?>
<div class="card">
<h2><?= $row ? 'Edit Bus' : 'Add Bus' ?></h2>
<form method="post">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string)($row['id'] ?? 0)) ?>">
<div class="row">
<p><label>Bus Number/Name</label><input name="bus_number" required value="<?= e($row['bus_number'] ?? '') ?>"></p>
<p><label>Plate Number</label><input name="plate_number" required value="<?= e($row['plate_number'] ?? '') ?>"></p>
<p><label>Capacity</label><input type="number" name="capacity" min="1" required value="<?= e((string)($row['capacity'] ?? '')) ?>"></p>
<p><label>Model</label><input name="model" value="<?= e($row['model'] ?? '') ?>"></p>
<p><label>Status</label><select name="status"><option value="active">Active</option><option value="maintenance" <?= (($row['status'] ?? '')==='maintenance')?'selected':'' ?>>Maintenance</option><option value="inactive" <?= (($row['status'] ?? '')==='inactive')?'selected':'' ?>>Inactive</option></select></p>
<p><label>LATRA Licence Expiry</label><input type="date" name="latra_license_expiry" value="<?= e($row['latra_license_expiry'] ?? '') ?>"></p>
<p><label>Insurance Expiry</label><input type="date" name="insurance_expiry" value="<?= e($row['insurance_expiry'] ?? '') ?>"></p>
<p><label>Next Service Date</label><input type="date" name="next_service_date" value="<?= e($row['next_service_date'] ?? '') ?>"></p>
</div>
<p><label>Notes</label><textarea name="notes"><?= e($row['notes'] ?? '') ?></textarea></p>
<button class="btn" type="submit">Save Bus</button> <a class="btn secondary" href="<?= e(url('buses.php')) ?>">Cancel</a>
</form>
</div>
<?php endif; ?>
<div class="card"><h2>Fleet List</h2><div class="table-wrap"><table><thead><tr><th>Bus</th><th>Plate</th><th>Capacity</th><th>Status</th><th>Licence Expiry</th><th>Insurance</th><th>Next Service</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['bus_number']) ?></td><td><?= e($r['plate_number']) ?></td><td><?= e((string)$r['capacity']) ?></td><td><span class="badge"><?= e($r['status']) ?></span></td><td><?= e($r['latra_license_expiry'] ?? '') ?></td><td><?= e($r['insurance_expiry'] ?? '') ?></td><td><?= e($r['next_service_date'] ?? '') ?></td><td class="actions"><a class="btn small" href="<?= e(url('buses.php?action=edit&id='.$r['id'])) ?>">Edit</a><?php if(can_delete_records()): ?><a class="btn small danger" onclick="return confirm('Delete bus?')" href="<?= e(url('buses.php?action=delete&id='.$r['id'])) ?>">Delete</a><?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="8">No buses yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
