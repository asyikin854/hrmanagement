<?php
// Daily job (Plesk > Scheduled Tasks). Re-checks payments CHIP hasn't confirmed to us yet and emails renewal
// reminders 14 days before an account expires. Run with PHP on the server, or fetch
// https://hrmanagement.esrmy.com/app/cron.php?token=<cron_token from esr-config.php>.
declare(strict_types=1);
require __DIR__ . '/lib/billing.php';

if (PHP_SAPI !== 'cli') {
    $token = (string)cfg('cron_token');
    if ($token === '' || !hash_equals($token, (string)($_GET['token'] ?? ''))) {
        fail_page(403, 'Forbidden');
    }
}
header('Content-Type: text/plain; charset=utf-8');

$open = q("SELECT purchase_id FROM payments WHERE status = 'created' AND purchase_id IS NOT NULL AND created_at > ?",
    [date('Y-m-d H:i:s', time() - 3 * 86400)])->fetchAll(PDO::FETCH_COLUMN);
foreach ($open as $pid) {
    try {
        echo $pid, ': ', process_purchase($pid), "\n";
    } catch (Throwable $e) {
        echo $pid, ': error ', $e->getMessage(), "\n";
    }
}

$site = rtrim((string)cfg('site'), '/');
$due = q("SELECT t.*, u.email, u.name FROM tenants t JOIN users u ON u.tenant_id = t.id AND u.role = 'owner'
          WHERE t.status = 'active' AND t.expires_at BETWEEN ? AND ? AND (t.reminded_for IS NULL OR t.reminded_for <> t.expires_at)",
    [now(), date('Y-m-d H:i:s', time() + 14 * 86400)])->fetchAll();
foreach ($due as $t) {
    $en = $t['lang'] === 'en';
    $until = date('j/n/Y', strtotime($t['expires_at']));
    $link = $site . ($en ? '/en/subscribe/' : '/langgan/') . '?plan=' . $t['plan'] . '#form';
    $ok = $en
        ? send_mail($t['email'], 'Your subscription ends on ' . $until,
            "Hi {$t['name']},\n\nThe subscription for {$t['company']} ends on $until. Renew here, using this same email ({$t['email']}) so the time is added to your account:\n$link\n\nYour data stays safe; if the subscription lapses you can still renew later.\n\nExcellent Synergy Resources\nWhatsApp 011-1632 0694")
        : send_mail($t['email'], 'Langganan anda tamat pada ' . $until,
            "Hai {$t['name']},\n\nLangganan untuk {$t['company']} tamat pada $until. Perbaharui di sini, menggunakan e-mel yang sama ({$t['email']}) supaya tempoh ditambah ke akaun anda:\n$link\n\nData anda kekal selamat; jika langganan tamat, anda masih boleh memperbaharuinya kemudian.\n\nExcellent Synergy Resources\nWhatsApp 011-1632 0694");
    if ($ok) {
        q('UPDATE tenants SET reminded_for = expires_at WHERE id = ?', [$t['id']]);
    }
    echo 'reminder ', $t['email'], ': ', $ok ? 'sent' : 'failed', "\n";
}
echo "done\n";
