<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function setting_get(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = db()->query('SELECT setting_key, setting_value FROM company_settings')->fetchAll();
            foreach ($rows as $row) {
                $cache[$row['setting_key']] = (string)$row['setting_value'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function setting_set(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO company_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

function company_profile(): array
{
    return [
        'name' => setting_get('company_name', 'Bus Owner Control Company'),
        'phone' => setting_get('company_phone', '+255 700 000 000'),
        'email' => setting_get('company_email', 'info@example.com'),
        'address' => setting_get('company_address', 'Dar es Salaam, Tanzania'),
        'footer' => setting_get('receipt_footer', 'Thank you for travelling with us.'),
        'country_code' => setting_get('whatsapp_country_code', '255'),
    ];
}

function latest_license(): ?array
{
    try {
        $row = db()->query('SELECT * FROM subscription_license ORDER BY id DESC LIMIT 1')->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function license_state_label(?array $license = null): array
{
    $license = $license ?: latest_license();
    if (!$license) {
        return ['status' => 'missing', 'label' => 'No licence record', 'class' => 'danger', 'days_left' => null];
    }
    $status = $license['status'] ?? 'trial';
    $daysLeft = null;
    if (!empty($license['expiry_date'])) {
        $today = new DateTimeImmutable(date('Y-m-d'));
        $expiry = new DateTimeImmutable($license['expiry_date']);
        $daysLeft = (int)$today->diff($expiry)->format('%r%a');
        if ($daysLeft < 0 && !in_array($status, ['expired','suspended'], true)) {
            $status = 'expired';
        }
    }
    $classes = ['trial' => 'warning', 'active' => 'success', 'expired' => 'danger', 'suspended' => 'danger', 'missing' => 'danger'];
    $label = ucfirst($status);
    if ($daysLeft !== null) {
        $label .= $daysLeft >= 0 ? ' · ' . $daysLeft . ' days left' : ' · expired ' . abs($daysLeft) . ' days ago';
    }
    return ['status' => $status, 'label' => $label, 'class' => $classes[$status] ?? 'warning', 'days_left' => $daysLeft];
}

function normalize_phone_for_whatsapp(?string $phone, string $countryCode = '255'): string
{
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '0')) {
        return $countryCode . substr($digits, 1);
    }
    if (strlen($digits) === 9) {
        return $countryCode . $digits;
    }
    return $digits;
}

function whatsapp_url(?string $phone, string $message): string
{
    $profile = company_profile();
    $digits = normalize_phone_for_whatsapp($phone, $profile['country_code']);
    if ($digits === '') {
        return '#';
    }
    return 'https://wa.me/' . rawurlencode($digits) . '?text=' . rawurlencode($message);
}

function ticket_number(int $bookingId): string
{
    return 'TKT-' . date('Y') . '-' . str_pad((string)$bookingId, 6, '0', STR_PAD_LEFT);
}

function receipt_number(string $prefix, int $id): string
{
    return strtoupper($prefix) . '-' . date('Y') . '-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
}

function csv_download(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}
