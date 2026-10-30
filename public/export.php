<?php
require_once __DIR__ . '/../app/core/phase2.php';
require_once __DIR__ . '/../app/core/saas.php';
require_login();
require_feature('exports');
$type = get_value('type','reports');
$from = get_value('from', date('Y-m-01'));
$to = get_value('to', date('Y-m-d'));
$pdo = db();

function export_agent_clause(string $alias = ''): array {
    $prefix = $alias ? $alias.'.' : '';
    if (is_branch_scoped_user()) { require_branch_assignment(); return [' AND '.$prefix.'agent_id=?', [user_agent_id()]]; }
    $agentId = (int)get_value('agent_id','0');
    return $agentId>0 ? [' AND '.$prefix.'agent_id=?', [$agentId]] : ['', []];
}

audit_log('export', 'exports', null, 'CSV export downloaded: '.$type, null, $_GET);

if ($type === 'cash_closings') {
    require_roles(['owner','admin','manager','accountant','agent']);
    [$agentSql,$agentParams] = export_agent_clause('c');
    $st=$pdo->prepare('SELECT c.closing_date,COALESCE(a.branch,"Direct office") branch,COALESCE(a.name,"") agent_name,c.total_cash_expected,c.cash_submitted,c.mobile_money_submitted,c.bank_submitted,c.expenses_paid,c.variance,c.status,c.notes FROM cash_closings c LEFT JOIN agents a ON c.agent_id=a.id WHERE c.closing_date BETWEEN ? AND ?'.$agentSql.' ORDER BY c.closing_date DESC');
    $st->execute(array_merge([$from,$to],$agentParams));
    $rows=[]; foreach($st->fetchAll() as $r){$rows[]=[$r['closing_date'],$r['branch'],$r['agent_name'],$r['total_cash_expected'],$r['cash_submitted'],$r['mobile_money_submitted'],$r['bank_submitted'],$r['expenses_paid'],$r['variance'],$r['status'],$r['notes']];}
    csv_download('cash_closings_'.$from.'_to_'.$to.'.csv',['Date','Branch','Agent','Expected','Cash Submitted','Mobile Submitted','Bank Submitted','Expenses Paid','Variance','Status','Notes'],$rows);
}

if ($type === 'reconciliation') {
    require_roles(['owner','admin','manager','accountant','agent']);
    $agents = get_agents_for_scope();
    $agentFilter = (int)get_value('agent_id','0');
    $rows=[];
    foreach($agents as $a){ if($agentFilter>0 && (int)$a['id']!==$agentFilter) continue; $e=expected_collections($from,$to,(int)$a['id']); $st=$pdo->prepare('SELECT COALESCE(SUM(cash_submitted+mobile_money_submitted+bank_submitted),0) submitted, COALESCE(SUM(expenses_paid),0) expenses_paid FROM cash_closings WHERE closing_date BETWEEN ? AND ? AND agent_id=?'); $st->execute([$from,$to,(int)$a['id']]); $c=$st->fetch(); $commission=((float)$a['commission_rate']/100)*$e['total_expected']; $variance=(float)$c['submitted']-($e['total_expected']-(float)$c['expenses_paid']); $rows[]=[$a['branch'],$a['name'],$e['ticket']['count'],$e['ticket']['total'],$e['parcel']['count'],$e['parcel']['total'],$e['total_expected'],$c['submitted'],$c['expenses_paid'],$commission,$variance]; }
    csv_download('agent_reconciliation_'.$from.'_to_'.$to.'.csv',['Branch','Agent','Ticket Count','Ticket Sales','Parcel Count','Parcel Sales','Expected','Submitted','Expenses Paid','Commission','Variance'],$rows);
}

if ($type === 'maintenance') {
    require_roles(['owner','admin','manager']);
    $busId=(int)get_value('bus_id','0'); $status=get_value('status',''); $where=['m.service_date BETWEEN ? AND ?']; $params=[$from,$to]; if($busId>0){$where[]='m.bus_id=?';$params[]=$busId;} if($status!==''){$where[]='m.status=?';$params[]=$status;}
    $st=$pdo->prepare('SELECT m.service_date,b.bus_number,b.plate_number,m.maintenance_type,m.description,m.vendor,m.cost,m.next_due_date,m.status FROM maintenance_records m JOIN buses b ON m.bus_id=b.id WHERE '.implode(' AND ',$where).' ORDER BY m.service_date DESC'); $st->execute($params); $rows=[]; foreach($st->fetchAll() as $r){$rows[]=array_values($r);} csv_download('maintenance_'.$from.'_to_'.$to.'.csv',['Date','Bus','Plate','Type','Description','Vendor','Cost','Next Due','Status'],$rows);
}

if ($type === 'fuel') {
    require_roles(['owner','admin','manager']);
    $busId=(int)get_value('bus_id','0'); $where=['f.fuel_date BETWEEN ? AND ?']; $params=[$from,$to]; if($busId>0){$where[]='f.bus_id=?';$params[]=$busId;}
    $st=$pdo->prepare('SELECT f.fuel_date,b.bus_number,b.plate_number,f.litres,f.price_per_litre,f.total_amount,f.odometer_km,f.station,f.notes FROM fuel_logs f JOIN buses b ON f.bus_id=b.id WHERE '.implode(' AND ',$where).' ORDER BY f.fuel_date DESC'); $st->execute($params); $rows=[]; foreach($st->fetchAll() as $r){$rows[]=array_values($r);} csv_download('fuel_'.$from.'_to_'.$to.'.csv',['Date','Bus','Plate','Litres','Price Per Litre','Total','Odometer','Station','Notes'],$rows);
}

if ($type === 'reports') {
    require_roles(['owner','admin','manager','accountant','agent','parcel_officer']);
    require_once __DIR__ . '/../app/core/reports_data.php';
    $rows=[]; foreach($trips as $t){ $revenue=(float)$t['ticket_sales']+(float)$t['parcel_sales']; $rows[]=[app_date($t['departure_date']),substr($t['departure_time'],0,5),$t['origin'].' to '.$t['destination'],$t['bus_number'].' '.$t['plate_number'],$t['seats_sold'],$t['ticket_sales'],$t['parcel_sales'],$t['trip_expenses'],$revenue-(float)$t['trip_expenses'],$t['status']]; }
    csv_download('business_report_'.$from.'_to_'.$to.'.csv',['Date','Time','Route','Bus','Seats','Ticket Sales','Parcel Sales','Expenses','Profit','Status'],$rows);
}

http_response_code(400); exit('Unknown export type.');
