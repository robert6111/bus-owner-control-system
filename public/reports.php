<?php require_once __DIR__ . '/../app/core/reports_data.php';
require_once __DIR__ . '/../app/core/saas.php';
?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title report-screen-title no-print">
    <div>
        <h1><?= e($scope ? 'My Branch Reports' : 'Business Reports') ?></h1>
        <p class="help-text">Use filters on screen. Download Clean Report opens a report-only page with no sidebar, menu, filters, or screenshot-like controls.</p>
    </div>
    <div class="report-toolbar">
        <?php
        $cleanReportUrl = url('reports_print.php');
        if (!empty($_GET)) {
            $cleanReportUrl .= '?' . http_build_query($_GET);
        }
        ?>
        <a class="btn" href="<?= e($cleanReportUrl) ?>" target="_blank" rel="noopener">Download Clean Report</a>
        <?php if (feature_enabled('exports')): ?><a class="btn success" href="<?= e(url('export.php?type=reports&' . http_build_query($_GET ?: ['from'=>$from,'to'=>$to]))) ?>">Export CSV</a><?php endif; ?>
    </div>
</div>
<?php if($scope): ?><div class="alert alert-warning no-print">This report is scoped to your assigned branch/agent only. Company expenses and full-company profit are hidden.</div><?php endif; ?>

<div class="card filter-card no-print">
<form method="get">
<div class="filter-grid">
<p><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></p>
<p><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></p>
<p><label>Route</label><select name="route_id"><option value="0">All routes</option><?php foreach($routes as $r): ?><option value="<?= e((string)$r['id']) ?>" <?= selected($routeId,$r['id']) ?>><?= e($r['origin'].' → '.$r['destination']) ?></option><?php endforeach; ?></select></p>
<p><label>Bus</label><select name="bus_id"><option value="0">All buses</option><?php foreach($buses as $b): ?><option value="<?= e((string)$b['id']) ?>" <?= selected($busId,$b['id']) ?>><?= e($b['bus_number'].' '.$b['plate_number']) ?></option><?php endforeach; ?></select></p>
<p><label>Agent / Branch</label><select name="agent_id" <?= $scope ? 'disabled' : '' ?>><option value="0">All agents/branches</option><?php foreach($agentsList as $a): ?><option value="<?= e((string)$a['id']) ?>" <?= selected($agentId,$a['id']) ?>><?= e($a['branch'].' - '.$a['name']) ?></option><?php endforeach; ?></select><?php if($scope): ?><input type="hidden" name="agent_id" value="<?= e((string)$agentId) ?>"><?php endif; ?></p>
<p><label>Trip Status</label><select name="trip_status"><option value="">All trip statuses</option><?php foreach($tripStatuses as $s): ?><option value="<?= e($s) ?>" <?= selected($tripStatus,$s) ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select></p>
<p><label>Payment Method</label><select name="payment_method"><option value="">All payment methods</option><?php foreach($paymentMethods as $m): ?><option value="<?= e($m) ?>" <?= selected($paymentMethod,$m) ?>><?= e(str_replace('_',' ',ucfirst($m))) ?></option><?php endforeach; ?></select></p>
<p><label>Expense Category</label><select name="expense_category" <?= ($scope || !can_manage_finance()) ? 'disabled' : '' ?>><option value="">All expense categories</option><?php foreach($expenseCategories as $c): ?><option value="<?= e($c) ?>" <?= selected($expenseCategory,$c) ?>><?= e(str_replace('_',' ',ucfirst($c))) ?></option><?php endforeach; ?></select></p>
</div>
<div class="filter-actions"><button class="btn secondary">Run Report</button> <a class="btn secondary" href="<?= e(url('reports.php')) ?>">Reset</a></div>
</form>
</div>

<?php include __DIR__ . '/../app/views/report_document.php'; ?>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
