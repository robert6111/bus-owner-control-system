<?php
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/saas.php';
require_once __DIR__ . '/../app/core/csrf.php';
require_roles(['owner','admin']);
require_feature('users');

$pdo = db();
$action = get_value('action','list');
$id = (int)get_value('id','0');
$roles = ['owner','admin','manager','accountant','agent','parcel_officer'];

if (is_post()) {
    verify_csrf();
    $id = (int)post_value('id','0');
    $role = post_value('role','manager');
    if (!in_array($role, $roles, true)) {
        $role = 'manager';
    }
    $agentId = post_value('agent_id') ? (int)post_value('agent_id') : null;
    if (!in_array($role, ['agent','parcel_officer'], true)) {
        $agentId = null;
    }
    $status = post_value('status','active');
    $password = post_value('password');

    if ($id > 0) {
        if ($id === (int)current_user()['id'] && $status !== 'active') {
            flash('danger', 'You cannot deactivate your own account while logged in.');
            redirect('users.php?action=edit&id=' . $id);
        }
        $activeOwners = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE role="owner" AND status="active"')->fetchColumn();
        $st = $pdo->prepare('SELECT role,status FROM users WHERE id=?');
        $st->execute([$id]);
        $old = $st->fetch();
        if ($old && $old['role'] === 'owner' && $old['status'] === 'active' && ($role !== 'owner' || $status !== 'active') && $activeOwners <= 1) {
            flash('danger', 'At least one active owner account must remain.');
            redirect('users.php?action=edit&id=' . $id);
        }
        if ($old && $old['status'] !== 'active' && $status === 'active') {
            require_limit_available('users');
        }

        $oldSnapshot = audit_record_snapshot('users', $id);
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET name=?, email=?, role=?, agent_id=?, status=?, password_hash=? WHERE id=?');
            $stmt->execute([post_value('name'), strtolower(post_value('email')), $role, $agentId, $status, $hash, $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET name=?, email=?, role=?, agent_id=?, status=? WHERE id=?');
            $stmt->execute([post_value('name'), strtolower(post_value('email')), $role, $agentId, $status, $id]);
        }
        $newSnapshot = audit_record_snapshot('users', $id);
        audit_log('update', 'users', $id, 'User account updated: ' . strtolower(post_value('email')), $oldSnapshot, $newSnapshot);
        flash('success','User updated.');
    } else {
        require_limit_available('users');
        if ($password === '') {
            // No shared default password: generate a random one-time password for the new account.
            $password = bin2hex(random_bytes(6));
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (name,email,password_hash,role,agent_id,status) VALUES (?,?,?,?,?,?)');
        $stmt->execute([post_value('name'), strtolower(post_value('email')), $hash, $role, $agentId, $status]);
        $newId = (int)$pdo->lastInsertId();
        audit_log('create', 'users', $newId, 'User account created: ' . strtolower(post_value('email')), null, audit_record_snapshot('users', $newId));
        flash('success','User created. Temporary password (shown once, ask the user to change it): ' . $password);
    }
    redirect('users.php');
}

if ($action === 'deactivate' && $id > 0) {
    if ($id === (int)current_user()['id']) {
        flash('danger','You cannot deactivate your own account.');
    } else {
        $old = audit_record_snapshot('users', $id);
        $pdo->prepare('UPDATE users SET status="inactive" WHERE id=?')->execute([$id]);
        audit_log('deactivate', 'users', $id, 'User account deactivated', $old, audit_record_snapshot('users', $id));
        flash('success','User deactivated.');
    }
    redirect('users.php');
}

if ($action === 'activate' && $id > 0) {
    require_limit_available('users');
    $old = audit_record_snapshot('users', $id);
    $pdo->prepare('UPDATE users SET status="active" WHERE id=?')->execute([$id]);
    audit_log('activate', 'users', $id, 'User account activated', $old, audit_record_snapshot('users', $id));
    flash('success','User activated.');
    redirect('users.php');
}

$row = null;
if ($action === 'edit' && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM users WHERE id=?');
    $st->execute([$id]);
    $row = $st->fetch();
}
$agents = $pdo->query('SELECT id,name,branch FROM agents WHERE status="active" ORDER BY branch,name')->fetchAll();
$rows = $pdo->query('SELECT u.*,a.name agent_name,a.branch FROM users u LEFT JOIN agents a ON u.agent_id=a.id ORDER BY FIELD(u.role,"owner","admin","manager","accountant","agent","parcel_officer"), u.name')->fetchAll();
?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title"><h1>Users & Role Access</h1><a class="btn" href="<?= e(url('users.php?action=create')) ?>">Add User</a></div>
<div class="card">
<h2>Role meaning</h2>
<div class="table-wrap"><table><thead><tr><th>Role</th><th>Access</th></tr></thead><tbody>
<tr><td><strong>Owner</strong></td><td>Everything: dashboard, finance, users, audit logs, delete actions.</td></tr>
<tr><td><strong>Admin</strong></td><td>System administration, users, audit logs, delete actions.</td></tr>
<tr><td><strong>Manager</strong></td><td>Operations: buses, routes, drivers, trips, bookings, parcels, expenses, reports. No users/delete by default.</td></tr>
<tr><td><strong>Accountant</strong></td><td>Finance: expenses, reports, collections, passenger/parcel viewing. No operational setup.</td></tr>
<tr><td><strong>Ticket Agent</strong></td><td>Only their assigned branch/agent passenger seats, parcels, and branch reports.</td></tr>
<tr><td><strong>Parcel Officer</strong></td><td>Only their assigned branch/agent parcel records and parcel reports.</td></tr>
</tbody></table></div></div>
<?php if($action==='create'||$action==='edit'): ?>
<div class="card"><h2><?= $row?'Edit User':'Add User' ?></h2><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string)($row['id']??0)) ?>">
<div class="row"><p><label>Name</label><input name="name" required value="<?= e($row['name']??'') ?>"></p><p><label>Email</label><input type="email" name="email" required value="<?= e($row['email']??'') ?>"></p><p><label>Role</label><select name="role" required><?php foreach($roles as $role): ?><option value="<?= e($role) ?>" <?= selected($row['role']??'manager', $role) ?>><?= e(role_label($role)) ?></option><?php endforeach; ?></select></p><p><label>Assign Branch/Agent for Ticket Agent or Parcel Officer</label><select name="agent_id"><option value="">-- No branch assignment --</option><?php foreach($agents as $a): ?><option value="<?= e((string)$a['id']) ?>" <?= selected($row['agent_id']??'', $a['id']) ?>><?= e($a['branch'].' - '.$a['name']) ?></option><?php endforeach; ?></select></p><p><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive" <?= selected($row['status']??'', 'inactive') ?>>Inactive</option></select></p><p><label><?= $row ? 'New Password Optional' : 'Password Optional' ?></label><input type="text" name="password" placeholder="Leave blank for a random one-time password on new user"></p></div><button class="btn">Save User</button> <a class="btn secondary" href="<?= e(url('users.php')) ?>">Cancel</a></form></div>
<?php endif; ?>
<div class="card"><h2>User List</h2><div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Branch Scope</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['email']) ?></td><td><span class="badge"><?= e(role_label($r['role'])) ?></span></td><td><?= e(($r['branch']??'') ? $r['branch'].' - '.$r['agent_name'] : 'All / Not scoped') ?></td><td><?= e($r['status']) ?></td><td><?= e($r['last_login_at']??'') ?></td><td class="actions"><a class="btn small" href="<?= e(url('users.php?action=edit&id='.$r['id'])) ?>">Edit</a><?php if($r['status']==='active'): ?><a class="btn small danger" onclick="return confirm('Deactivate this user?')" href="<?= e(url('users.php?action=deactivate&id='.$r['id'])) ?>">Deactivate</a><?php else: ?><a class="btn small success" href="<?= e(url('users.php?action=activate&id='.$r['id'])) ?>">Activate</a><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="7">No users found.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
