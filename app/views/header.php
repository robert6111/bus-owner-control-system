<?php require_once __DIR__ . '/../core/auth.php'; require_once __DIR__ . '/../core/settings.php'; require_once __DIR__ . '/../core/saas.php'; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?></title>
    <?php $cssVersion = @filemtime(__DIR__ . '/../../public/assets/css/style.css') ?: time(); ?>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css') . '?v=' . $cssVersion) ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-mark">B</div>
            <div>
                <strong>Bus Owner</strong>
                <span>Control System</span>
            </div>
        </div>
        <nav id="main-nav">
            <a href="<?= e(url('dashboard.php')) ?>">Dashboard</a>
            <?php if (feature_enabled('trips')): ?><a href="<?= e(url('trips.php')) ?>">Trips</a><?php endif; ?>
            <?php if (feature_enabled('bookings') && has_role(['owner','admin','manager','agent','accountant'])): ?>
                <a href="<?= e(url('bookings.php')) ?>">Passenger Seats</a>
            <?php endif; ?>
            <?php if (feature_enabled('parcels') && has_role(['owner','admin','manager','agent','parcel_officer','accountant'])): ?>
                <a href="<?= e(url('parcels.php')) ?>">Parcels</a>
            <?php endif; ?>
            <?php if (can_manage_finance()): ?>
                <a href="<?= e(url('expenses.php')) ?>">Expenses</a>
            <?php endif; ?>
            <?php if (feature_enabled('reports') && can_view_reports()): ?>
                <a href="<?= e(url('reports.php')) ?>">Reports</a>
            <?php endif; ?>
            <?php if (feature_enabled('cash_closing') && can_manage_finance()): ?>
                <a href="<?= e(url('cash_closings.php')) ?>">Daily Cash Closing</a>
            <?php endif; ?>
            <?php if (feature_enabled('reconciliation') && can_manage_finance()): ?>
                <a href="<?= e(url('reconciliation.php')) ?>">Agent Reconciliation</a>
            <?php endif; ?>
            <?php if (feature_enabled('maintenance') && has_role(['owner','admin','manager'])): ?>
                <a href="<?= e(url('maintenance.php')) ?>">Maintenance</a>
            <?php endif; ?>
            <?php if (feature_enabled('fuel') && has_role(['owner','admin','manager'])): ?>
                <a href="<?= e(url('fuel.php')) ?>">Fuel Tracking</a>
            <?php endif; ?>
            <?php if (feature_enabled('notifications') && has_role(['owner','admin','manager','agent','parcel_officer'])): ?>
                <a href="<?= e(url('notifications.php')) ?>">Notifications</a>
            <?php endif; ?>
            <?php if (can_manage()): ?>
                <hr>
                <?php if (feature_enabled('buses')): ?><a href="<?= e(url('buses.php')) ?>">Buses</a><?php endif; ?>
                <?php if (feature_enabled('routes')): ?><a href="<?= e(url('routes.php')) ?>">Routes</a><?php endif; ?>
                <?php if (feature_enabled('drivers')): ?><a href="<?= e(url('drivers.php')) ?>">Drivers</a><?php endif; ?>
                <?php if (feature_enabled('agents')): ?><a href="<?= e(url('agents.php')) ?>">Agents/Branches</a><?php endif; ?>
            <?php endif; ?>
            <?php if (can_manage_users()): ?>
                <hr>
                <?php if (feature_enabled('users')): ?><a href="<?= e(url('users.php')) ?>">Users & Roles</a><?php endif; ?>
                <?php if (feature_enabled('audit_logs')): ?><a href="<?= e(url('audit_logs.php')) ?>">Audit Logs</a><?php endif; ?>
                <a href="<?= e(url('packages.php')) ?>">Packages & Licence</a>
                <?php if (feature_enabled('settings')): ?><a href="<?= e(url('settings.php')) ?>">Company Settings</a><?php endif; ?>
                <?php if (feature_enabled('backup')): ?><a href="<?= e(url('backup.php')) ?>">Backup</a><?php endif; ?>
            <?php endif; ?>
            <hr>
            <a href="<?= e(url('logout.php')) ?>">Logout</a>
        </nav>
    </aside>
    <button class="sidebar-overlay no-print" type="button" aria-label="Close navigation"></button>
    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle no-print" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="sidebar">
                    <span></span><span></span><span></span>
                </button>
                <div>
                    <strong><?= e(APP_NAME) ?></strong>
                    <small><?= e(APP_VERSION) ?></small>
                </div>
            </div>
            <?php $user = current_user(); if ($user): $lic = license_state_label(); $pkg = package_badge(); ?>
                <div class="topbar-right">
                    <span class="license-chip <?= e($pkg['class']) ?>"><?= e($pkg['package']) ?></span>
                    <span class="license-chip <?= e($lic['class']) ?>"><?= e($lic['label']) ?></span>
                    <div class="user-chip"><?= e($user['name']) ?> · <?= e(role_label($user['role'])) ?></div>
                </div>
            <?php endif; ?>
        </header>
        <section class="content">
            <?php $healthIssues = (has_role(['owner','admin']) ? saas_check_setup_health() : []); if ($healthIssues): ?>
                <div class="alert alert-warning"><strong>SaaS setup warning:</strong> <?= e(implode(' | ', $healthIssues)) ?>. The app is checking database <strong><?= e(defined('DB_NAME') ? DB_NAME : 'unknown') ?></strong>. Import the migration into that same database or run <a href="<?= e(url('saas_repair.php')) ?>">SaaS Repair</a>.</div>
            <?php endif; ?>
            <?php show_flash(); ?>
