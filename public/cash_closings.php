<?php
require_once __DIR__ . '/../app/core/phase2.php';
require_once __DIR__ . '/../app/core/saas.php';
require_once __DIR__ . '/../app/core/csrf.php';
require_roles(['owner','admin','manager','accountant','agent']);
require_feature('cash_closing');
$pdo = db();
$action = get_value('action','list');
$id = (int)get_value('id','0');
$today = date('Y-m-d');
$from = get_value('from', date('Y-m-01'));
$to = get_value('to', $today);
[$filterAgentId] = phase2_agent_filter((int)get_value('agent_id','0'));
$agents = get_agents_for_scope();

if ($action === 'expected') {
    header('Content-Type: application/json');
    $date = get_value('closing_date', $today);
    [$agentId] = phase2_agent_filter((int)get_value('agent_id','0'));
    echo json_encode(expected_collections($date, $date, $agentId));
    exit;
}

if (is_post()) {
    verify_csrf();
    $id = (int)post_value('id','0');
    $closingDate = post_value('closing_date', $today);
    [$agentId] = phase2_agent_filter(post_value('agent_id') ? (int)post_value('agent_id') : null);
    $expected = expected_collections($closingDate, $closingDate, $agentId);
    $cashSubmitted = (float)(post_value('cash_submitted') ?: 0);
    $mobileSubmitted = (float)(post_value('mobile_money_submitted') ?: 0);
    $bankSubmitted = (float)(post_value('bank_submitted') ?: 0);
    $expensesPaid = (float)(post_value('expenses_paid') ?: 0);
    $variance = ($cashSubmitted + $mobileSubmitted + $bankSubmitted) - ($expected['total_expected'] - $expensesPaid);
    $status = post_value('status','submitted');
    if (!in_array($status, ['draft','submitted','approved'], true)) { $status = 'submitted'; }
    if ($status === 'approved' && !has_role(['owner','admin','manager','accountant'])) { $status = 'submitted'; }
    $approvedBy = $status === 'approved' ? (current_user()['id'] ?? null) : null;
    $data = [$closingDate, $agentId, $expected['ticket']['cash'], $expected['parcel']['cash'], $expected['total_expected'], $cashSubmitted, $mobileSubmitted, $bankSubmitted, $expensesPaid, $variance, $status, current_user()['id'] ?? null, $approvedBy, post_value('notes')];
    if ($id > 0) {
        $old = audit_record_snapshot('cash_closings', $id);
        $data[] = $id;
        $pdo->prepare('UPDATE cash_closings SET closing_date=?,agent_id=?,ticket_cash_expected=?,parcel_cash_expected=?,total_cash_expected=?,cash_submitted=?,mobile_money_submitted=?,bank_submitted=?,expenses_paid=?,variance=?,status=?,prepared_by=?,approved_by=?,notes=? WHERE id=?')->execute($data);
        audit_log('update', 'cash_closings', $id, 'Daily cash closing updated for ' . $closingDate, $old, audit_record_snapshot('cash_closings', $id));
        flash('success','Cash closing updated.');
    } else {
        $pdo->prepare('INSERT INTO cash_closings (closing_date,agent_id,ticket_cash_expected,parcel_cash_expected,total_cash_expected,cash_submitted,mobile_money_submitted,bank_submitted,expenses_paid,variance,status,prepared_by,approved_by,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($data);
        $newId = (int)$pdo->lastInsertId();
        audit_log('create', 'cash_closings', $newId, 'Daily cash closing created for ' . $closingDate, null, audit_record_snapshot('cash_closings', $newId));
        flash('success','Cash closing saved.');
    }
    redirect('cash_closings.php?from=' . $closingDate . '&to=' . $closingDate);
}

if ($action === 'delete' && $id > 0) {
    require_roles(['owner','admin']);
    $old = audit_record_snapshot('cash_closings', $id);
    $pdo->prepare('DELETE FROM cash_closings WHERE id=?')->execute([$id]);
    audit_log('delete','cash_closings',$id,'Cash closing deleted', $old, null);
    flash('success','Cash closing deleted.');
    redirect('cash_closings.php');
}

$row = null;
if ($action === 'edit' && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM cash_closings WHERE id=?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) { flash('danger','Cash closing not found.'); redirect('cash_closings.php'); }
    if (is_branch_scoped_user() && (int)$row['agent_id'] !== (int)user_agent_id()) { http_response_code(403); exit('You can only edit your branch closing.'); }
}

$where = ['c.closing_date BETWEEN ? AND ?'];
$params = [$from,$to];
if ($filterAgentId) { $where[]='c.agent_id=?'; $params[]=$filterAgentId; }
$whereSql = implode(' AND ', $where);
$st = $pdo->prepare('SELECT c.*,a.branch,a.name agent_name,u.name prepared_name,ap.name approved_name FROM cash_closings c LEFT JOIN agents a ON c.agent_id=a.id LEFT JOIN users u ON c.prepared_by=u.id LEFT JOIN users ap ON c.approved_by=ap.id WHERE ' . $whereSql . ' ORDER BY c.closing_date DESC,c.id DESC');
$st->execute($params);
$rows = $st->fetchAll();
$summary = ['expected'=>0,'submitted'=>0,'variance'=>0];
foreach ($rows as $r) { $summary['expected'] += (float)$r['total_cash_expected']; $summary['submitted'] += (float)$r['cash_submitted'] + (float)$r['mobile_money_submitted'] + (float)$r['bank_submitted']; $summary['variance'] += (float)$r['variance']; }
$defaultExpected = expected_collections($today, $today, $filterAgentId);
?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title"><h1>Daily Cash Closing</h1><a class="btn" href="<?= e(url('cash_closings.php?action=create')) ?>">New Closing</a></div>
<div class="grid grid-3"><div class="stat"><span>Expected collections</span><strong><?= e(money($summary['expected'])) ?></strong></div><div class="stat"><span>Submitted</span><strong><?= e(money($summary['submitted'])) ?></strong></div><div class="stat"><span>Variance</span><strong class="<?= $summary['variance'] < 0 ? 'amount-negative':'amount-positive' ?>"><?= e(money($summary['variance'])) ?></strong></div></div>
<div class="card"><form method="get"><div class="row"><p><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></p><p><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></p><p><label>Agent/Branch</label><select name="agent_id"><option value="0">All / Direct Office</option><?php foreach($agents as $a): ?><option value="<?= e((string)$a['id']) ?>" <?= selected($filterAgentId,$a['id']) ?>><?= e($a['branch'].' - '.$a['name']) ?></option><?php endforeach; ?></select></p></div><button class="btn secondary">Filter</button> <?php if (feature_enabled('exports')): ?><a class="btn success" href="<?= e(url('export.php?type=cash_closings&from='.$from.'&to='.$to.'&agent_id=' . (int)$filterAgentId)) ?>">Export CSV</a><?php endif; ?></form></div>
<?php if($action==='create'||$action==='edit'): $editDate=$row['closing_date']??$today; $editAgent=(int)($row['agent_id']??($filterAgentId??0)); $expected=expected_collections($editDate,$editDate,$editAgent?:null); ?>
<div class="card"><h2><?= $row?'Edit Cash Closing':'New Cash Closing' ?></h2><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string)($row['id']??0)) ?>"><div class="row"><p><label>Closing Date</label><input type="date" name="closing_date" value="<?= e($editDate) ?>" required></p><p><label>Agent/Branch</label><?php if(is_branch_scoped_user()): ?><input value="<?= e(($agents[0]['branch']??'').' - '.($agents[0]['name']??'')) ?>" disabled><input type="hidden" name="agent_id" value="<?= e((string)user_agent_id()) ?>"><?php else: ?><select name="agent_id"><option value="">Direct office / all company</option><?php foreach($agents as $a): ?><option value="<?= e((string)$a['id']) ?>" <?= selected($editAgent,$a['id']) ?>><?= e($a['branch'].' - '.$a['name']) ?></option><?php endforeach; ?></select><?php endif; ?></p><p><label>Expected Ticket Cash</label><input value="<?= e(money($expected['ticket']['cash'])) ?>" disabled></p><p><label>Expected Parcel Cash</label><input value="<?= e(money($expected['parcel']['cash'])) ?>" disabled></p><p><label>Cash Submitted</label><input type="number" step="0.01" name="cash_submitted" value="<?= e((string)($row['cash_submitted']??$expected['cash_expected'])) ?>"></p><p><label>Mobile Money Submitted</label><input type="number" step="0.01" name="mobile_money_submitted" value="<?= e((string)($row['mobile_money_submitted']??$expected['mobile_expected'])) ?>"></p><p><label>Bank Submitted</label><input type="number" step="0.01" name="bank_submitted" value="<?= e((string)($row['bank_submitted']??$expected['bank_expected'])) ?>"></p><p><label>Expenses Paid From Collections</label><input type="number" step="0.01" name="expenses_paid" value="<?= e((string)($row['expenses_paid']??0)) ?>"></p><p><label>Status</label><select name="status"><option value="draft">Draft</option><option value="submitted" <?= selected($row['status']??'submitted','submitted') ?>>Submitted</option><?php if(has_role(['owner','admin','manager','accountant'])): ?><option value="approved" <?= selected($row['status']??'','approved') ?>>Approved</option><?php endif; ?></select></p></div><p><label>Notes</label><textarea name="notes"><?= e($row['notes']??'') ?></textarea></p><button class="btn">Save Closing</button> <a class="btn secondary" href="<?= e(url('cash_closings.php')) ?>">Cancel</a></form></div>
<?php endif; ?>
<div class="card"><h2>Closing Records</h2><div class="table-wrap"><table><thead><tr><th>Date</th><th>Agent/Branch</th><th>Expected Collections</th><th>Submitted Total</th><th>Expenses Paid</th><th>Variance</th><th>Status</th><th>Prepared By</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $r): $submitted=(float)$r['cash_submitted']+(float)$r['mobile_money_submitted']+(float)$r['bank_submitted']; ?><tr><td><?= e(app_date($r['closing_date'])) ?></td><td><?= e(($r['branch']??'Direct office').' '.($r['agent_name']??'')) ?></td><td><?= e(money($r['total_cash_expected'])) ?></td><td><?= e(money($submitted)) ?></td><td><?= e(money($r['expenses_paid'])) ?></td><td><strong class="<?= (float)$r['variance'] < 0 ? 'amount-negative':'amount-positive' ?>"><?= e(money($r['variance'])) ?></strong></td><td><span class="badge"><?= e($r['status']) ?></span></td><td><?= e($r['prepared_name']??'') ?></td><td class="actions"><a class="btn small" href="<?= e(url('cash_closings.php?action=edit&id='.$r['id'])) ?>">Edit</a><?php if(can_delete_records()): ?><a class="btn small danger" onclick="return confirm('Delete this cash closing?')" href="<?= e(url('cash_closings.php?action=delete&id='.$r['id'])) ?>">Delete</a><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="9">No cash closings found.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
