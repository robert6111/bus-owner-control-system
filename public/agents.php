<?php
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/saas.php';
require_once __DIR__ . '/../app/core/csrf.php';
require_manager();
require_feature('agents');
$pdo = db();
$action = get_value('action','list');
$id = (int)get_value('id','0');

if (is_post()) {
    verify_csrf();
    $id = (int)post_value('id','0');
    $data = [post_value('name'), post_value('phone'), post_value('branch'), post_value('commission_rate') ?: 0, post_value('status','active')];
    if ($id > 0) {
        $old = audit_record_snapshot('agents', $id);
        if (($old['status'] ?? '') !== 'active' && post_value('status','active') === 'active') { require_limit_available('branches'); }
        $data[] = $id;
        $pdo->prepare('UPDATE agents SET name=?,phone=?,branch=?,commission_rate=?,status=? WHERE id=?')->execute($data);
        $new = audit_record_snapshot('agents', $id);
        audit_log('update','agents',$id,'Agent/branch updated: ' . post_value('branch') . ' - ' . post_value('name'), $old, $new);
        flash('success','Agent updated.');
    } else {
        require_limit_available('branches');
        $pdo->prepare('INSERT INTO agents (name,phone,branch,commission_rate,status) VALUES (?,?,?,?,?)')->execute($data);
        $newId = (int)$pdo->lastInsertId();
        audit_log('create','agents',$newId,'Agent/branch added: ' . post_value('branch') . ' - ' . post_value('name'), null, audit_record_snapshot('agents', $newId));
        flash('success','Agent added.');
    }
    redirect('agents.php');
}

if ($action === 'delete' && $id > 0 && can_delete_records()) {
    $old = audit_record_snapshot('agents', $id);
    try {
        $pdo->prepare('DELETE FROM agents WHERE id=?')->execute([$id]);
        audit_log('delete','agents',$id,'Agent/branch deleted', $old, null);
        flash('success','Agent deleted.');
    } catch(Throwable $e) {
        flash('danger','Cannot delete agent because it has bookings or parcels.');
    }
    redirect('agents.php');
}

$row = null;
if ($action === 'edit' && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM agents WHERE id=?');
    $st->execute([$id]);
    $row = $st->fetch();
}
$rows = $pdo->query('SELECT * FROM agents ORDER BY branch,name')->fetchAll();
?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title"><h1>Agents / Branches</h1><a class="btn" href="<?= e(url('agents.php?action=create')) ?>">Add Agent</a></div>
<?php if($action==='create'||$action==='edit'): ?><div class="card"><h2><?= $row?'Edit Agent':'Add Agent' ?></h2><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string)($row['id']??0)) ?>"><div class="row"><p><label>Name</label><input name="name" required value="<?= e($row['name']??'') ?>"></p><p><label>Phone</label><input name="phone" value="<?= e($row['phone']??'') ?>"></p><p><label>Branch/Office</label><input name="branch" required value="<?= e($row['branch']??'') ?>"></p><p><label>Commission Rate %</label><input type="number" step="0.01" name="commission_rate" value="<?= e((string)($row['commission_rate']??0)) ?>"></p><p><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive" <?= (($row['status']??'')==='inactive')?'selected':'' ?>>Inactive</option></select></p></div><button class="btn">Save Agent</button> <a class="btn secondary" href="<?= e(url('agents.php')) ?>">Cancel</a></form></div><?php endif; ?>
<div class="card"><h2>Agent List</h2><div class="table-wrap"><table><thead><tr><th>Name</th><th>Branch</th><th>Phone</th><th>Commission</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['branch']) ?></td><td><?= e($r['phone']) ?></td><td><?= e((string)$r['commission_rate']) ?>%</td><td><span class="badge"><?= e($r['status']) ?></span></td><td class="actions"><a class="btn small" href="<?= e(url('agents.php?action=edit&id='.$r['id'])) ?>">Edit</a><?php if(can_delete_records()): ?><a class="btn small danger" onclick="return confirm('Delete agent?')" href="<?= e(url('agents.php?action=delete&id='.$r['id'])) ?>">Delete</a><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="6">No agents yet.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
