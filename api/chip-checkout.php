<?php
// Creates a CHIP purchase for a subscription plan and sends the customer to CHIP's FPX payment page.
// The purchase is recorded in the database; when CHIP confirms the payment (api/chip-callback.php) the
// customer's account is created or extended. Keys and passwords are in esr-config.php / chip-config.php
// one folder above the web root, never in this repository (see DEPLOY.md).
declare(strict_types=1);
require __DIR__ . '/../app/lib/bootstrap.php';

function form_url(string $lang, string $plan, string $status = ''): string
{
    $q = ['plan' => $plan];
    if ($status !== '') {
        $q['status'] = $status;
    }
    return rtrim((string)cfg('site'), '/') . ($lang === 'en' ? '/en/subscribe/' : '/langgan/') . '?' . http_build_query($q) . '#form';
}

function back(string $lang, string $plan, string $status): void
{
    header('Location: ' . form_url($lang, $plan, $status), true, 303);
    exit;
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
$site = rtrim((string)cfg('site'), '/');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . $site . '/#pricing', true, 303);
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
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$tel = phone((string)($_POST['phone'] ?? ''));
if ($name === '' || $company === '' || mb_strlen($name) > 128 || mb_strlen($company) > 128 || strlen($email) > 160
    || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($tel) < 10 || strlen($tel) > 16) {
    back($lang, $plan, 'invalid');
}

// At most 10 payment attempts per IP an hour, so the form can't be used to flood CHIP or the payments table
$withDb = is_array(cfg('db')) && !empty(cfg('db')['name']);
if ($withDb) {
    $recent = (int)q("SELECT COUNT(*) FROM login_attempts WHERE email = 'checkout' AND ip = ? AND at > ?", [client_ip(), date('Y-m-d H:i:s', time() - 3600)])->fetchColumn();
    if ($recent >= 10) {
        back($lang, $plan, 'error');
    }
    q("INSERT INTO login_attempts (ip, email, at) VALUES (?, 'checkout', ?)", [client_ip(), now()]);
}

$key = (string)cfg('chip_api_key');
$brand = (string)cfg('chip_brand_id');
if ($key === '' || $brand === '') {
    error_log('chip-checkout: CHIP API key or brand ID is not set on the server');
    back($lang, $plan, 'error');
}

// Without a database ($withDb false) the payment still works, but the account has to be set up by hand

$p = PLANS[$plan];
$body = [
    'brand_id' => $brand,
    'reference' => 'ESR-' . $p['code'] . '-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(2)),
    'client' => ['email' => $email, 'full_name' => $name, 'legal_name' => $company, 'phone' => $tel],
    'purchase' => ['currency' => 'MYR', 'products' => [['name' => $p[$lang], 'price' => $p['price']]]],
    'send_receipt' => true,
    'success_redirect' => $site . ($lang === 'en' ? '/en/subscription-confirmed/' : '/langganan-berjaya/'),
    'failure_redirect' => form_url($lang, $plan, 'failed'),
    'cancel_redirect' => form_url($lang, $plan, 'cancelled'),
];
if ($withDb) {
    $body['success_callback'] = $site . '/api/chip-callback.php';
}

$ch = curl_init('https://gate.chip-in.asia/api/v1/purchases/');
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

if ($withDb) {
    try {
        q('INSERT INTO payments (purchase_id, email, name, company, phone, plan, amount_sen, status, lang, created_at)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [(string)$data['id'], $email, $name, $company, $tel, $plan, $p['price'], 'created', $lang, now()]);
    } catch (Throwable $e) {
        error_log('chip-checkout: could not record purchase ' . ($data['id'] ?? '') . ': ' . $e->getMessage());
        back($lang, $plan, 'error');
    }
}
header('Location: ' . $url, true, 303);
