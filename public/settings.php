<?php
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/csrf.php';
require_once __DIR__ . '/../app/core/settings.php';
require_once __DIR__ . '/../app/core/saas.php';
require_roles(['owner','admin']);
require_feature('settings');
$pdo = db();

if (is_post()) {
    verify_csrf();
    $oldSettings = [
        'company_name' => setting_get('company_name'),
        'company_phone' => setting_get('company_phone'),
        'company_email' => setting_get('company_email'),
        'company_address' => setting_get('company_address'),
        'receipt_footer' => setting_get('receipt_footer'),
        'whatsapp_country_code' => setting_get('whatsapp_country_code'),
        'backup_retention_days' => setting_get('backup_retention_days'),
    ];
    foreach ($oldSettings as $key => $oldValue) {
        setting_set($key, post_value($key, $oldValue));
    }
    audit_log('update', 'company_settings', null, 'Company branding/settings updated', $oldSettings, [
        'company_name' => post_value('company_name'),
        'company_phone' => post_value('company_phone'),
        'company_email' => post_value('company_email'),
        'company_address' => post_value('company_address'),
        'receipt_footer' => post_value('receipt_footer'),
        'whatsapp_country_code' => post_value('whatsapp_country_code'),
        'backup_retention_days' => post_value('backup_retention_days'),
    ]);
    flash('success', 'Company settings updated. Package/licence is managed separately under Packages & Licence.');
    redirect('settings.php');
}

$profile = company_profile();
$license = saas_current_license() ?: [];
$licenseState = saas_license_status($license);
$supportState = saas_support_status($license);
$currentTier = package_tier();
?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title"><h1>Company Settings</h1><a class="btn" href="<?= e(url('packages.php')) ?>">Packages & Licence</a></div>
<div class="card">
<p class="help-text">This controls the company branding used on tickets, parcel receipts, professional reports, WhatsApp messages, backups, and documents. Package and feature limits are now managed from Packages & Licence.</p>
<form method="post"><?= csrf_field() ?>
<h2>Branding & receipt details</h2>
<div class="row">
<p><label>Company Name</label><input name="company_name" required value="<?= e($profile['name']) ?>"></p>
<p><label>Company Phone</label><input name="company_phone" value="<?= e($profile['phone']) ?>"></p>
<p><label>Company Email</label><input type="email" name="company_email" value="<?= e($profile['email']) ?>"></p>
<p><label>WhatsApp Country Code</label><input name="whatsapp_country_code" value="<?= e($profile['country_code']) ?>" placeholder="255"></p>
<p><label>Backup Retention Days</label><input type="number" name="backup_retention_days" value="<?= e(setting_get('backup_retention_days','30')) ?>"></p>
<p><label>Receipt Footer</label><input name="receipt_footer" value="<?= e($profile['footer']) ?>"></p>
</div>
<p><label>Company Address</label><textarea name="company_address"><?= e($profile['address']) ?></textarea></p>
<button class="btn">Save Company Settings</button>
</form>
</div>
<div class="card">
<h2>Current package summary</h2>
<div class="grid grid-4">
    <div class="stat"><span>Package</span><strong><?= e($currentTier['name']) ?></strong><small><?= e($license['billing_model'] ?? 'one_time_plus_optional_support') ?></small></div>
    <div class="stat"><span>Licence</span><strong><?= e($licenseState['label']) ?></strong><small><?= e($license['license_key'] ?? '') ?></small></div>
    <div class="stat"><span>Setup Fee</span><strong><?= e(money($license['setup_fee_amount'] ?? $currentTier['setup_fee'])) ?></strong><small>Paid: <?= e($license['setup_fee_paid'] ?? 'partial') ?></small></div>
    <div class="stat"><span>Optional Monthly</span><strong><?= e(money($license['optional_monthly_fee'] ?? $currentTier['optional_monthly_fee'])) ?></strong><small><?= e($supportState['label']) ?></small></div>
</div>
<p><a class="btn" href="<?= e(url('packages.php')) ?>">Manage Packages & Licence</a></p>
</div>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
