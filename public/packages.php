<?php
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/csrf.php';
require_once __DIR__ . '/../app/core/settings.php';
require_once __DIR__ . '/../app/core/saas.php';
require_roles(['owner','admin']);
$pdo = db();
$tiers = package_tiers();
$license = saas_current_license() ?: [];
$action = get_value('action','');

if (is_post()) {
    verify_csrf();
    $packageCode = post_value('package_code', current_package_code());
    if (!isset($tiers[$packageCode])) {
        flash('danger', 'Invalid package selected.');
        redirect('packages.php');
    }
    $tier = $tiers[$packageCode];
    $old = $license;
    $licenseId = (int)($license['id'] ?? 0);
    $supportEnabled = post_value('optional_monthly_enabled','0') === '1' ? 1 : 0;
    $supportStatus = $supportEnabled ? post_value('support_status','active') : 'off';
    if (!in_array($supportStatus, ['off','active','paused','expired'], true)) $supportStatus = $supportEnabled ? 'active' : 'off';
    $status = post_value('status','active');
    if (!in_array($status, ['trial','active','expired','suspended'], true)) $status = 'trial';
    $billing = post_value('billing_model','one_time_plus_optional_support');
    if (!in_array($billing, ['one_time_setup','one_time_plus_optional_support','recurring_subscription'], true)) $billing = 'one_time_plus_optional_support';
    $setupPaid = post_value('setup_fee_paid','partial');
    if (!in_array($setupPaid, ['no','partial','yes'], true)) $setupPaid = 'partial';

    $data = [
        post_value('company_name', setting_get('company_name','Bus Company')),
        post_value('license_key', 'LIC-' . strtoupper($packageCode) . '-' . date('Ymd')),
        $tier['name'],
        $packageCode,
        $billing,
        $status,
        post_value('start_date') ?: null,
        post_value('expiry_date') ?: null,
        (float)post_value('setup_fee_amount', (string)$tier['setup_fee']),
        $setupPaid,
        (float)post_value('optional_monthly_fee', (string)$tier['optional_monthly_fee']),
        $supportEnabled,
        $supportStatus,
        post_value('support_start_date') ?: null,
        post_value('support_expiry_date') ?: null,
        (int)$tier['max_users'],
        (int)$tier['max_branches'],
        (int)$tier['max_buses'],
        (int)$tier['max_routes'],
        (int)$tier['max_monthly_trips'],
        post_value('notes'),
    ];

    if ($licenseId > 0) {
        $stmt = $pdo->prepare('UPDATE subscription_license SET company_name=?, license_key=?, plan=?, package_code=?, billing_model=?, status=?, start_date=?, expiry_date=?, setup_fee_amount=?, setup_fee_paid=?, optional_monthly_fee=?, optional_monthly_enabled=?, support_status=?, support_start_date=?, support_expiry_date=?, max_users=?, max_branches=?, max_buses=?, max_routes=?, max_monthly_trips=?, notes=? WHERE id=?');
        $stmt->execute([...$data, $licenseId]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO subscription_license (company_name, license_key, plan, package_code, billing_model, status, start_date, expiry_date, setup_fee_amount, setup_fee_paid, optional_monthly_fee, optional_monthly_enabled, support_status, support_start_date, support_expiry_date, max_users, max_branches, max_buses, max_routes, max_monthly_trips, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute($data);
        $licenseId = (int)$pdo->lastInsertId();
    }
    audit_log('update', 'subscription_license', $licenseId, 'Package/licence updated to ' . $tier['name'], $old, audit_record_snapshot('subscription_license', $licenseId));
    flash('success', 'Package/licence updated successfully.');
    redirect('packages.php');
}

$license = saas_current_license() ?: [];
$currentCode = current_package_code();
$currentTier = package_tier($currentCode);
$state = saas_license_status($license);
$support = saas_support_status($license);
$usage = package_usage_counts();
$resources = ['users'=>'Users','branches'=>'Branches/Agents','buses'=>'Buses','routes'=>'Routes','monthly_trips'=>'Trips this month'];
$featureLabels = saas_feature_labels();
?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title"><h1>Packages & Licence</h1></div>

<div class="card">
    <h2>Current package</h2>
    <div class="grid grid-4">
        <div class="stat"><span>Package</span><strong><?= e($currentTier['name'] ?? $currentCode) ?></strong><small><?= e($license['billing_model'] ?? 'one_time_plus_optional_support') ?></small></div>
        <div class="stat"><span>Licence</span><strong><?= e($state['label']) ?></strong><small><?= e($license['license_key'] ?? '') ?></small></div>
        <div class="stat"><span>Setup fee</span><strong><?= e(money($license['setup_fee_amount'] ?? $currentTier['setup_fee'])) ?></strong><small>Paid: <?= e($license['setup_fee_paid'] ?? 'partial') ?></small></div>
        <div class="stat"><span>Optional monthly</span><strong><?= e(money($license['optional_monthly_fee'] ?? $currentTier['optional_monthly_fee'])) ?></strong><small><?= e($support['label']) ?></small></div>
    </div>
    <p class="help-text">This follows your pricing model: charge a one-time setup fee based on the package, then keep monthly fees optional for support, backups, updates, cloud hosting, data entry, SMS/WhatsApp setup, or extra operational help.</p>
</div>

<div class="card">
    <h2>Usage against package limits</h2>
    <div class="table-wrap"><table><thead><tr><th>Resource</th><th>Used</th><th>Package Limit</th><th>Remaining</th><th>Status</th></tr></thead><tbody>
        <?php foreach($resources as $key=>$label): $st=package_limit_status($key); ?>
            <tr>
                <td><?= e($label) ?></td>
                <td><?= e(number_format($st['usage'])) ?></td>
                <td><?= e(limit_label($st['limit'])) ?></td>
                <td><?= e($st['unlimited'] ? 'Unlimited' : number_format($st['remaining'])) ?></td>
                <td><span class="badge <?= (!$st['unlimited'] && $st['percent']>=90) ? 'danger' : '' ?>"><?= e($st['unlimited'] ? 'OK' : $st['percent'].'% used') ?></span></td>
            </tr>
        <?php endforeach; ?>
    </tbody></table></div>
</div>

<div class="grid grid-4 package-grid">
<?php foreach($tiers as $code=>$tier): ?>
    <div class="card package-card <?= $code===$currentCode ? 'selected-package' : '' ?>">
        <h2><?= e($tier['name']) ?></h2>
        <p><?= e($tier['description']) ?></p>
        <p><strong><?= e(money($tier['setup_fee'])) ?></strong><br><small>one-time setup fee</small></p>
        <p><strong><?= e(money($tier['optional_monthly_fee'])) ?></strong><br><small>optional monthly support</small></p>
        <ul class="clean-list">
            <li><?= e(limit_label($tier['max_users'])) ?> users</li>
            <li><?= e(limit_label($tier['max_branches'])) ?> branches</li>
            <li><?= e(limit_label($tier['max_buses'])) ?> buses</li>
            <li><?= e(limit_label($tier['max_routes'])) ?> routes</li>
            <li><?= e(limit_label($tier['max_monthly_trips'])) ?> trips/month</li>
        </ul>
        <?php if($code===$currentCode): ?><span class="license-chip success">Current</span><?php endif; ?>
    </div>
<?php endforeach; ?>
</div>

<div class="card">
    <h2>Apply/change package</h2>
    <form method="post"><?= csrf_field() ?>
    <div class="row">
        <p><label>Client / Company Name</label><input name="company_name" required value="<?= e($license['company_name'] ?? setting_get('company_name','Bus Company')) ?>"></p>
        <p><label>Licence Key</label><input name="license_key" required value="<?= e($license['license_key'] ?? 'LIC-' . strtoupper($currentCode) . '-' . date('Ymd')) ?>"></p>
        <p><label>Package</label><select name="package_code" id="package_code"><?php foreach($tiers as $code=>$tier): ?><option value="<?= e($code) ?>" <?= selected($currentCode,$code) ?> data-setup="<?= e((string)$tier['setup_fee']) ?>" data-monthly="<?= e((string)$tier['optional_monthly_fee']) ?>"><?= e($tier['name']) ?></option><?php endforeach; ?></select></p>
        <p><label>Billing Model</label><select name="billing_model"><option value="one_time_plus_optional_support" <?= selected($license['billing_model'] ?? 'one_time_plus_optional_support','one_time_plus_optional_support') ?>>One-time setup + optional monthly</option><option value="one_time_setup" <?= selected($license['billing_model'] ?? '','one_time_setup') ?>>One-time setup only</option><option value="recurring_subscription" <?= selected($license['billing_model'] ?? '','recurring_subscription') ?>>Recurring subscription</option></select></p>
        <p><label>Licence Status</label><select name="status"><?php foreach(['trial','active','expired','suspended'] as $s): ?><option value="<?= e($s) ?>" <?= selected($state['status'],$s) ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select></p>
        <p><label>Start Date</label><input type="date" name="start_date" value="<?= e($license['start_date'] ?? date('Y-m-d')) ?>"></p>
        <p><label>Licence/Trial Expiry</label><input type="date" name="expiry_date" value="<?= e($license['expiry_date'] ?? date('Y-m-d', strtotime('+90 days'))) ?>"></p>
        <p><label>Setup Fee Amount</label><input type="number" step="0.01" name="setup_fee_amount" id="setup_fee_amount" value="<?= e((string)($license['setup_fee_amount'] ?? $currentTier['setup_fee'])) ?>"></p>
        <p><label>Setup Fee Paid?</label><select name="setup_fee_paid"><?php foreach(['no','partial','yes'] as $s): ?><option value="<?= e($s) ?>" <?= selected($license['setup_fee_paid'] ?? 'partial',$s) ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select></p>
        <p><label>Optional Monthly Support</label><select name="optional_monthly_enabled"><option value="0" <?= selected((string)($license['optional_monthly_enabled'] ?? '0'),'0') ?>>Off</option><option value="1" <?= selected((string)($license['optional_monthly_enabled'] ?? '0'),'1') ?>>On</option></select></p>
        <p><label>Monthly Fee Amount</label><input type="number" step="0.01" name="optional_monthly_fee" id="optional_monthly_fee" value="<?= e((string)($license['optional_monthly_fee'] ?? $currentTier['optional_monthly_fee'])) ?>"></p>
        <p><label>Support Status</label><select name="support_status"><?php foreach(['off','active','paused','expired'] as $s): ?><option value="<?= e($s) ?>" <?= selected($license['support_status'] ?? 'off',$s) ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select></p>
        <p><label>Support Start</label><input type="date" name="support_start_date" value="<?= e($license['support_start_date'] ?? date('Y-m-d')) ?>"></p>
        <p><label>Support Expiry</label><input type="date" name="support_expiry_date" value="<?= e($license['support_expiry_date'] ?? date('Y-m-d', strtotime('+30 days'))) ?>"></p>
    </div>
    <p><label>Licence Notes</label><textarea name="notes"><?= e($license['notes'] ?? '') ?></textarea></p>
    <button class="btn success">Save Package / Licence</button>
    </form>
</div>

<div class="card">
    <h2>Feature access by package</h2>
    <div class="table-wrap"><table><thead><tr><th>Feature</th><?php foreach($tiers as $tier): ?><th><?= e($tier['name']) ?></th><?php endforeach; ?></tr></thead><tbody>
    <?php foreach($featureLabels as $key=>$label): ?>
        <tr><td><?= e($label) ?></td><?php foreach($tiers as $tier): ?><td><?= in_array($key,$tier['features'],true) ? '<span class="badge success">Yes</span>' : '<span class="badge danger">No</span>' ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<script>
(function(){
  var sel=document.getElementById('package_code'); if(!sel) return;
  sel.addEventListener('change', function(){
    var opt=sel.options[sel.selectedIndex];
    var setup=document.getElementById('setup_fee_amount');
    var monthly=document.getElementById('optional_monthly_fee');
    if(setup) setup.value=opt.getAttribute('data-setup') || setup.value;
    if(monthly) monthly.value=opt.getAttribute('data-monthly') || monthly.value;
  });
})();
</script>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
