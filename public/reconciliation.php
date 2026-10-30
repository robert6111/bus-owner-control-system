<?php
require_once __DIR__ . '/../app/core/phase2.php';
require_once __DIR__ . '/../app/core/saas.php';
require_roles(['owner','admin','manager','accountant','agent']);
require_feature('reconciliation');
$pdo = db();
$from = get_value('from', date('Y-m-01'));
$to = get_value('to', date('Y-m-d'));
[$agentFilter] = phase2_agent_filter((int)get_value('agent_id','0'));
$agents = get_agents_for_scope();

$agentRows = $agentFilter ? array_filter($agents, fn($a)=>(int)$a['id']===(int)$agentFilter) : $agents;
$results = [];
foreach ($agentRows as $a) {
    $agentId = (int)$a['id'];
    $expected = expected_collections($from, $to, $agentId);
    $st = $pdo->prepare('SELECT COALESCE(SUM(cash_submitted),0) cash_submitted, COALESCE(SUM(mobile_money_submitted),0) mobile_submitted, COALESCE(SUM(bank_submitted),0) bank_submitted, COALESCE(SUM(expenses_paid),0) expenses_paid, COUNT(*) closing_count FROM cash_closings WHERE closing_date BETWEEN ? AND ? AND agent_id=?');
    $st->execute([$from,$to,$agentId]);
    $closing = $st->fetch() ?: [];
    $submitted = (float)$closing['cash_submitted'] + (float)$closing['mobile_submitted'] + (float)$closing['bank_submitted'];
    $commission = ((float)$a['commission_rate'] / 100) * $expected['total_expected'];
    $variance = $submitted - ($expected['total_expected'] - (float)$closing['expenses_paid']);
    $results[] = [
        'agent_id'=>$agentId,
        'branch'=>$a['branch'],
        'name'=>$a['name'],
        'rate'=>(float)$a['commission_rate'],
        'ticket'=>$expected['ticket']['total'],
        'ticket_count'=>$expected['ticket']['count'],
        'parcel'=>$expected['parcel']['total'],
        'parcel_count'=>$expected['parcel']['count'],
        'expected'=>$expected['total_expected'],
        'cash_expected'=>$expected['cash_expected'],
        'mobile_expected'=>$expected['mobile_expected'],
        'bank_expected'=>$expected['bank_expected'],
        'submitted'=>$submitted,
        'expenses_paid'=>(float)$closing['expenses_paid'],
        'commission'=>$commission,
        'variance'=>$variance,
        'closing_count'=>(int)$closing['closing_count'],
    ];
}
usort($results, fn($x,$y)=>$y['expected']<=>$x['expected']);
$totalExpected = array_sum(array_column($results,'expected'));
$totalSubmitted = array_sum(array_column($results,'submitted'));
$totalVariance = array_sum(array_column($results,'variance'));
if (!empty($_GET)) {
    audit_log('filter','reconciliation',null,'Agent reconciliation filtered',null,['from'=>$from,'to'=>$to,'agent_id'=>$agentFilter]);
}
?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title"><h1>Agent Reconciliation</h1><div class="actions"><?php if (feature_enabled('exports')): ?><a class="btn success" href="<?= e(url('export.php?type=reconciliation&from='.$from.'&to='.$to.'&agent_id='.(int)$agentFilter)) ?>">Export CSV</a><?php endif; ?><button class="btn secondary" onclick="window.print()">Print</button></div></div>
<div class="grid grid-3"><div class="stat"><span>Expected sales</span><strong><?= e(money($totalExpected)) ?></strong></div><div class="stat"><span>Submitted</span><strong><?= e(money($totalSubmitted)) ?></strong></div><div class="stat"><span>Variance</span><strong class="<?= $totalVariance < 0 ? 'amount-negative':'amount-positive' ?>"><?= e(money($totalVariance)) ?></strong></div></div>
<div class="card no-print"><form method="get"><div class="row"><p><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></p><p><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></p><p><label>Agent/Branch</label><select name="agent_id"><option value="0">All agents</option><?php foreach($agents as $a): ?><option value="<?= e((string)$a['id']) ?>" <?= selected($agentFilter,$a['id']) ?>><?= e($a['branch'].' - '.$a['name']) ?></option><?php endforeach; ?></select></p></div><button class="btn secondary">Filter</button></form></div>
<div class="card"><h2>Reconciliation Summary</h2><p class="help-text">Compares expected sales from passenger seats and parcels against submitted daily closings. Negative variance means money is short.</p><div class="table-wrap"><table><thead><tr><th>Agent/Branch</th><th>Tickets</th><th>Parcels</th><th>Expected</th><th>Cash</th><th>Mobile</th><th>Bank</th><th>Closings</th><th>Submitted</th><th>Expenses</th><th>Commission</th><th>Variance</th></tr></thead><tbody><?php foreach($results as $r): ?><tr><td><strong><?= e($r['branch']) ?></strong><br><?= e($r['name']) ?></td><td><?= e((string)$r['ticket_count']) ?><br><?= e(money($r['ticket'])) ?></td><td><?= e((string)$r['parcel_count']) ?><br><?= e(money($r['parcel'])) ?></td><td><strong><?= e(money($r['expected'])) ?></strong></td><td><?= e(money($r['cash_expected'])) ?></td><td><?= e(money($r['mobile_expected'])) ?></td><td><?= e(money($r['bank_expected'])) ?></td><td><?= e((string)$r['closing_count']) ?></td><td><?= e(money($r['submitted'])) ?></td><td><?= e(money($r['expenses_paid'])) ?></td><td><?= e(money($r['commission'])) ?><br><small><?= e((string)$r['rate']) ?>%</small></td><td><strong class="<?= $r['variance'] < 0 ? 'amount-negative':'amount-positive' ?>"><?= e(money($r['variance'])) ?></strong></td></tr><?php endforeach; ?><?php if(!$results): ?><tr><td colspan="12">No agent records found.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
