<?php
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/csrf.php';
require_roles(['owner','admin','manager','accountant']);

$pdo = db();
$action = get_value('action','list');
$id = (int)get_value('id','0');
$canWrite = can_manage_finance();

if (is_post()) {
    verify_csrf();
    $id = (int)post_value('id','0');
    $data = [post_value('trip_id') ? (int)post_value('trip_id') : null, post_value('bus_id') ? (int)post_value('bus_id') : null, post_value('category'), post_value('amount') ?: 0, post_value('expense_date'), post_value('paid_by'), post_value('notes')];
    if ($id > 0) {
        $old = audit_record_snapshot('expenses', $id);
        $data[] = $id;
        $pdo->prepare('UPDATE expenses SET trip_id=?,bus_id=?,category=?,amount=?,expense_date=?,paid_by=?,notes=? WHERE id=?')->execute($data);
        $new = audit_record_snapshot('expenses', $id);
        audit_log('update', 'expenses', $id, 'Expense updated: ' . post_value('category') . ' ' . money(post_value('amount') ?: 0), $old, $new);
        flash('success','Expense updated.');
    } else {
        $pdo->prepare('INSERT INTO expenses (trip_id,bus_id,category,amount,expense_date,paid_by,notes) VALUES (?,?,?,?,?,?,?)')->execute($data);
        $newId = (int)$pdo->lastInsertId();
        audit_log('create', 'expenses', $newId, 'Expense recorded: ' . post_value('category') . ' ' . money(post_value('amount') ?: 0), null, audit_record_snapshot('expenses', $newId));
        flash('success','Expense recorded.');
    }
    redirect('expenses.php');
}

if ($action === 'delete' && $id > 0) {
    require_roles(['owner','admin']);
    $old = audit_record_snapshot('expenses', $id);
    $pdo->prepare('DELETE FROM expenses WHERE id=?')->execute([$id]);
    audit_log('delete', 'expenses', $id, 'Expense deleted', $old, null);
    flash('success','Expense deleted.');
    redirect('expenses.php');
}

$row = null;
if ($action === 'edit' && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM expenses WHERE id=?');
    $st->execute([$id]);
    $row = $st->fetch();
}
$trips = $pdo->query('SELECT t.id,t.departure_date,r.origin,r.destination,b.bus_number FROM trips t JOIN routes r ON t.route_id=r.id JOIN buses b ON t.bus_id=b.id ORDER BY t.departure_date DESC LIMIT 200')->fetchAll();
$buses = $pdo->query('SELECT * FROM buses ORDER BY bus_number')->fetchAll();
$from = get_value('from', date('Y-m-01'));
$to = get_value('to', date('Y-m-d'));
$st = $pdo->prepare('SELECT e.*,b.bus_number,r.origin,r.destination,t.departure_date FROM expenses e LEFT JOIN buses b ON e.bus_id=b.id LEFT JOIN trips t ON e.trip_id=t.id LEFT JOIN routes r ON t.route_id=r.id WHERE e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date DESC,e.id DESC');
$st->execute([$from,$to]);
$rows = $st->fetchAll();
?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title"><h1>Expenses</h1><?php if($canWrite): ?><a class="btn" href="<?= e(url('expenses.php?action=create')) ?>">Record Expense</a><?php endif; ?></div>
<?php if($action==='create'||$action==='edit'): ?>
<div class="card"><h2><?= $row?'Edit Expense':'Record Expense' ?></h2><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string)($row['id']??0)) ?>"><div class="row"><p><label>Trip Optional</label><select name="trip_id"><option value="">-- Not trip-specific --</option><?php foreach($trips as $t): ?><option value="<?= e((string)$t['id']) ?>" <?= selected($row['trip_id']??'', $t['id']) ?>><?= e(app_date($t['departure_date']).' '.$t['origin'].' → '.$t['destination'].' '.$t['bus_number']) ?></option><?php endforeach; ?></select></p><p><label>Bus Optional</label><select name="bus_id"><option value="">-- Select bus --</option><?php foreach($buses as $b): ?><option value="<?= e((string)$b['id']) ?>" <?= selected($row['bus_id']??'', $b['id']) ?>><?= e($b['bus_number'].' '.$b['plate_number']) ?></option><?php endforeach; ?></select></p><p><label>Category</label><select name="category"><option value="fuel">Fuel</option><option value="repair" <?= selected($row['category']??'', 'repair') ?>>Repair</option><option value="tyres" <?= selected($row['category']??'', 'tyres') ?>>Tyres</option><option value="allowance" <?= selected($row['category']??'', 'allowance') ?>>Driver/Conductor allowance</option><option value="office" <?= selected($row['category']??'', 'office') ?>>Office</option><option value="tax_fee" <?= selected($row['category']??'', 'tax_fee') ?>>Tax/Fee</option><option value="other" <?= selected($row['category']??'', 'other') ?>>Other</option></select></p><p><label>Amount</label><input type="number" step="0.01" name="amount" required value="<?= e((string)($row['amount']??0)) ?>"></p><p><label>Expense Date</label><input type="date" name="expense_date" required value="<?= e($row['expense_date']??date('Y-m-d')) ?>"></p><p><label>Paid By</label><input name="paid_by" value="<?= e($row['paid_by']??'') ?>"></p></div><p><label>Notes</label><textarea name="notes"><?= e($row['notes']??'') ?></textarea></p><button class="btn">Save Expense</button> <a class="btn secondary" href="<?= e(url('expenses.php')) ?>">Cancel</a></form></div>
<?php endif; ?>
<div class="card"><form method="get"><div class="row"><p><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></p><p><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></p></div><button class="btn secondary">Filter</button></form></div>
<div class="card"><h2>Expense Records</h2><div class="table-wrap"><table><thead><tr><th>Date</th><th>Category</th><th>Bus/Trip</th><th>Amount</th><th>Paid By</th><th>Notes</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e(app_date($r['expense_date'])) ?></td><td><?= e($r['category']) ?></td><td><?= e(($r['bus_number']??'').' '.(($r['origin']??'') ? $r['origin'].' → '.$r['destination'] : '')) ?></td><td><?= e(money($r['amount'])) ?></td><td><?= e($r['paid_by']) ?></td><td><?= e($r['notes']) ?></td><td class="actions"><a class="btn small" href="<?= e(url('expenses.php?action=edit&id='.$r['id'])) ?>">Edit</a><?php if(can_delete_records()): ?><a class="btn small danger" onclick="return confirm('Delete expense?')" href="<?= e(url('expenses.php?action=delete&id='.$r['id'])) ?>">Delete</a><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="7">No expenses found.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
