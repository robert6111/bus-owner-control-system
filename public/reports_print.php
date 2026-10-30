<?php require_once __DIR__ . '/../app/core/reports_data.php';
require_once __DIR__ . '/../app/core/saas.php';
audit_log('export', 'reports', null, 'Clean report export opened', null, [
    'from' => $from,
    'to' => $to,
    'route_id' => $routeId ?: null,
    'bus_id' => $busId ?: null,
    'agent_id' => $agentId ?: null,
    'trip_status' => $tripStatus ?: null,
    'payment_method' => $paymentMethod ?: null,
    'expense_category' => $expenseCategory ?: null,
]);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($reportTitle) ?> - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="report-print-page">
    <div class="report-export-actions no-print">
        <button class="btn" onclick="window.print()">Download / Save as PDF</button>
        <a class="btn secondary" href="<?= e(url('reports.php') . (!empty($_GET) ? '?' . http_build_query($_GET) : '')) ?>">Back to Reports</a>
    </div>
    <main class="clean-report-page">
        <?php include __DIR__ . '/../app/views/report_document.php'; ?>
    </main>
</body>
</html>
