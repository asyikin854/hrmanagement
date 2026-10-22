<?php
// CHIP payments and account provisioning. A payment only counts once CHIP's own API says it is paid,
// so a forged callback or a visit to the thank-you page can never create an account.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

const CHIP_API = 'https://gate.chip-in.asia/api/v1/';

function chip_request(string $method, string $path, ?array $body = null): array
{
    $key = (string)cfg('chip_api_key');
    if ($key === '') {
        throw new RuntimeException('CHIP API key is not set on the server');
    }
    $ch = curl_init(CHIP_API . $path);
    $opts = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $data = is_string($res) ? json_decode($res, true) : null;
    if ($code < 200 || $code >= 300 || !is_array($data)) {
        throw new RuntimeException('CHIP HTTP ' . $code . ': ' . substr((string)$res, 0, 300));
    }
    return $data;
}

function add_months(string $from, int $months): string
{
    $d = new DateTime($from);
    $day = (int)$d->format('j');
    $d->modify('first day of +' . $months . ' month');
    $d->setDate((int)$d->format('Y'), (int)$d->format('n'), min($day, (int)$d->format('t')));
    return $d->format('Y-m-d H:i:s');
}

// Called by the CHIP callback, the cron job and the admin "check with CHIP" button.
// Returns a short status: paid-new, paid-renewal, already, review, not-paid, unknown.
function process_purchase(string $purchaseId): string
{
    if (!preg_match('/^[a-f0-9-]{36}$/', $purchaseId)) {
        return 'unknown';
    }
    $p = chip_request('GET', 'purchases/' . $purchaseId . '/');
    if (($p['brand_id'] ?? '') !== cfg('chip_brand_id')) {
        error_log('esr billing: purchase ' . $purchaseId . ' belongs to another brand');
        return 'unknown';
    }
    $pay = q('SELECT * FROM payments WHERE purchase_id = ?', [$purchaseId])->fetch();
    if (!$pay) {
        return 'unknown'; // only purchases made through our checkout are handled
    }
    $status = (string)($p['status'] ?? '');
    if ($status !== 'paid') {
        if (in_array($status, ['error', 'cancelled', 'expired', 'blocked'], true) && $pay['status'] === 'created') {
            q("UPDATE payments SET status = 'failed', note = ? WHERE id = ? AND status = 'created'", ['CHIP: ' . $status, $pay['id']]);
        }
        return 'not-paid';
    }
    $plan = PLANS[$pay['plan']] ?? null;
    $total = (int)($p['purchase']['total'] ?? -1);
    if (!$plan || $total !== (int)$pay['amount_sen'] || $total !== $plan['price'] || ($p['purchase']['currency'] ?? '') !== 'MYR') {
        q("UPDATE payments SET status = 'review', note = ? WHERE id = ? AND status IN ('created','failed')",
            ['Amount does not match the plan (' . $total . ' sen)', $pay['id']]);
        notify_owner('Payment needs review', "CHIP purchase $purchaseId was paid but the amount does not match the plan. Check it in the dashboard.");
        return 'review';
    }
    return provision($pay, $purchaseId);
}

function provision(array $pay, string $ref): string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pay = q('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$pay['id']])->fetch();
        if ($pay['status'] === 'paid') {
            $pdo->commit();
            return 'already';
        }
        $plan = PLANS[$pay['plan']];
        $email = strtolower(trim($pay['email']));
        $user = q('SELECT * FROM users WHERE email = ? FOR UPDATE', [$email])->fetch();
        $now = now();
        if ($user && $user['role'] !== 'owner') {
            q("UPDATE payments SET status = 'review', paid_at = ?, note = ? WHERE id = ?",
                [$now, 'Email already used by a ' . $user['role'] . ' account', $pay['id']]);
            $pdo->commit();
            notify_owner('Payment needs review', "{$pay['company']} ({$email}) paid " . rm((int)$pay['amount_sen'])
                . " but this email already belongs to a {$user['role']} account. Set up their account by hand in the dashboard.");
            return 'review';
        }
        $link = null;
        if (!$user) {
            $to = add_months($now, $plan['months']);
            q('INSERT INTO tenants (company, phone, plan, status, lang, starts_at, expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$pay['company'], $pay['phone'], $pay['plan'], 'active', $pay['lang'], $now, $to, $now]);
            $tid = (int)$pdo->lastInsertId();
            q('INSERT INTO users (tenant_id, email, name, role, created_at) VALUES (?, ?, ?, ?, ?)', [$tid, $email, $pay['name'], 'owner', $now]);
            $uid = (int)$pdo->lastInsertId();
            $from = $now;
            $kind = 'new';
            $link = make_password_link($uid, 14);
        } else {
            $tid = (int)$user['tenant_id'];
            $t = q('SELECT * FROM tenants WHERE id = ? FOR UPDATE', [$tid])->fetch();
            $from = strtotime($t['expires_at']) > time() ? $t['expires_at'] : $now;
            $to = add_months($from, $plan['months']);
            q("UPDATE tenants SET plan = ?, expires_at = ?, status = 'active', reminded_for = NULL WHERE id = ?", [$pay['plan'], $to, $tid]);
            $kind = 'renewal';
            if (!$user['password_hash']) {
                $link = make_password_link((int)$user['id'], 14);
            }
        }
        q("UPDATE payments SET status = 'paid', paid_at = ?, tenant_id = ?, kind = ?, period_from = ?, period_to = ? WHERE id = ?",
            [$now, $tid, $kind, $from, $to, $pay['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    welcome_mail($pay, $kind, $to, $link);
    notify_owner(($kind === 'new' ? 'New subscriber: ' : 'Renewal: ') . $pay['company'],
        "Company: {$pay['company']}\nName: {$pay['name']}\nEmail: {$pay['email']}\nPhone: {$pay['phone']}\nPlan: {$pay['plan']}\n"
        . 'Amount: ' . rm((int)$pay['amount_sen']) . "\nActive until: $to\nCHIP: $ref\n\n" . cfg('site') . '/admin/');
    return 'paid-' . $kind;
}

function welcome_mail(array $pay, string $kind, string $to, ?string $link): void
{
    $en = $pay['lang'] === 'en';
    $site = rtrim((string)cfg('site'), '/');
    $until = date('j/n/Y', strtotime($to));
    $planName = PLANS[$pay['plan']][$en ? 'en' : 'ms'];
    if ($en) {
        $subject = $kind === 'new' ? 'Your HR, payroll and finance account is ready' : 'Your subscription has been renewed';
        $body = "Hi {$pay['name']},\n\nThank you for your payment of " . rm((int)$pay['amount_sen']) . " for the $planName.\n"
            . "Your company account ({$pay['company']}) is active until $until.\n\n"
            . ($link ? "Set your password here (the link works for 14 days):\n$link\n\n" : "Log in here:\n$site/app/?lang=en\n\n")
            . "After logging in you can open the HR and Finance apps and invite your team.\n\n"
            . "Questions? Reply to this email or WhatsApp 011-1632 0694.\n\nExcellent Synergy Resources";
    } else {
        $subject = $kind === 'new' ? 'Akaun sistem HR, gaji dan kewangan anda sudah sedia' : 'Langganan anda telah diperbaharui';
        $body = "Hai {$pay['name']},\n\nTerima kasih atas bayaran " . rm((int)$pay['amount_sen']) . " untuk $planName.\n"
            . "Akaun syarikat anda ({$pay['company']}) aktif sehingga $until.\n\n"
            . ($link ? "Tetapkan kata laluan anda di sini (pautan sah selama 14 hari):\n$link\n\n" : "Log masuk di sini:\n$site/app/\n\n")
            . "Selepas log masuk, anda boleh membuka aplikasi HR dan Kewangan serta menjemput pasukan anda.\n\n"
            . "Ada soalan? Balas e-mel ini atau WhatsApp 011-1632 0694.\n\nExcellent Synergy Resources";
    }
    send_mail($pay['email'], $subject, $body);
}

function notify_owner(string $subject, string $body): void
{
    $to = (string)cfg('owner_email');
    if ($to !== '') {
        send_mail($to, '[ESR] ' . $subject, $body);
    }
}
