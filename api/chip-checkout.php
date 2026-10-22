<?php
// Creates a CHIP purchase for a subscription plan and sends the customer to CHIP's FPX payment page.
// The CHIP API key is NOT in this repository (it is public). On the server it is read from the
// CHIP_API_KEY / CHIP_BRAND_ID environment variables, or from chip-config.php one folder above the
// web root (see DEPLOY.md, "CHIP payments").
declare(strict_types=1);

const SITE = 'https://hrmanagement.esrmy.com';
const CHIP_PURCHASES = 'https://gate.chip-in.asia/api/v1/purchases/';
// Prices in sen, set here on the server so the form cannot change them.
const PLANS = [
    'quarterly' => ['price' => 55000, 'code' => 'Q',
        'ms' => 'Langganan 3 bulan: sistem HR, gaji dan kewangan',
        'en' => '3-month subscription: HR, payroll and finance system'],
    'yearly' => ['price' => 199900, 'code' => 'Y',
        'ms' => 'Langganan tahunan: sistem HR, gaji dan kewangan',
        'en' => 'Yearly subscription: HR, payroll and finance system'],
];

function form_url(string $lang, string $plan, string $status = ''): string
{
    $q = ['plan' => $plan];
    if ($status !== '') {
        $q['status'] = $status;
    }
    return SITE . ($lang === 'en' ? '/en/subscribe/' : '/langgan/') . '?' . http_build_query($q) . '#form';
}

function back(string $lang, string $plan, string $status): void
{
    header('Location: ' . form_url($lang, $plan, $status), true, 303);
    exit;
}

function config(): array
{
    $key = getenv('CHIP_API_KEY') ?: '';
    $brand = getenv('CHIP_BRAND_ID') ?: '';
    if ($key === '' || $brand === '') {
        $file = dirname((string)($_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/..')) . '/chip-config.php';
        if (is_file($file)) {
            $c = require $file;
            $key = (string)($c['api_key'] ?? '');
            $brand = (string)($c['brand_id'] ?? '');
        }
    }
    return [$key, $brand];
}

// Malaysian numbers to +60 format, as CHIP expects an international number.
function phone(string $raw): string
{
    $d = preg_replace('/\D+/', '', $raw) ?? '';
    if (str_starts_with($d, '60')) {
        return '+' . $d;
    }
    if (str_starts_with($d, '0')) {
        return '+6' . $d;
    }
    return '+' . $d;
}

header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . SITE . '/#pricing', true, 303);
    exit;
}

$lang = ($_POST['lang'] ?? '') === 'en' ? 'en' : 'ms';
$plan = (string)($_POST['plan'] ?? '');
if (!isset(PLANS[$plan])) {
    back($lang, 'yearly', 'invalid');
}
if ((string)($_POST['website'] ?? '') !== '') { // hidden field: only bots fill it in
    back($lang, $plan, 'invalid');
}

$name = trim((string)($_POST['name'] ?? ''));
$company = trim((string)($_POST['company'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$tel = phone((string)($_POST['phone'] ?? ''));
if ($name === '' || $company === '' || mb_strlen($name) > 128 || mb_strlen($company) > 128
    || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($tel) < 10 || strlen($tel) > 16) {
    back($lang, $plan, 'invalid');
}

[$key, $brand] = config();
if ($key === '' || $brand === '') {
    error_log('chip-checkout: CHIP API key or brand ID is not set on the server');
    back($lang, $plan, 'error');
}

$p = PLANS[$plan];
$body = [
    'brand_id' => $brand,
    'reference' => 'ESR-' . $p['code'] . '-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(2)),
    'client' => ['email' => $email, 'full_name' => $name, 'legal_name' => $company, 'phone' => $tel],
    'purchase' => ['currency' => 'MYR', 'products' => [['name' => $p[$lang], 'price' => $p['price']]]],
    'send_receipt' => true,
    'success_redirect' => SITE . ($lang === 'en' ? '/en/subscription-confirmed/' : '/langganan-berjaya/'),
    'failure_redirect' => form_url($lang, $plan, 'failed'),
    'cancel_redirect' => form_url($lang, $plan, 'cancelled'),
];

$ch = curl_init(CHIP_PURCHASES);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($body),
]);
$res = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

$data = is_string($res) ? json_decode($res, true) : null;
$url = is_array($data) ? (string)($data['checkout_url'] ?? '') : '';
if ($code < 200 || $code >= 300 || !str_starts_with($url, 'https://')) {
    error_log('chip-checkout: CHIP returned HTTP ' . $code . ': ' . substr((string)$res, 0, 500));
    back($lang, $plan, 'error');
}
header('Location: ' . $url, true, 303);
