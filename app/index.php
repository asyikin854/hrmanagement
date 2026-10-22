<?php
// Account home: plan and expiry, the apps, renewal and (for the owner) payment history.
declare(strict_types=1);
require __DIR__ . '/lib/layout.php';

$u = current_user();
if (!$u) {
    header('Location: /app/login.php', true, 303);
    exit;
}
if ($u['role'] === 'superadmin') {
    header('Location: /admin/', true, 303);
    exit;
}
$active = subscription_active($u);
$days = (int)floor((strtotime((string)$u['expires_at']) - time()) / 86400);
$isAdmin = in_array($u['role'], ['owner', 'admin'], true);
$plan = PLANS[$u['plan']] ?? PLANS['yearly'];
$renew = (lang() === 'en' ? '/en/subscribe/' : '/langgan/') . '?plan=' . rawurlencode((string)$u['plan']) . '#form';
$lq = 'lang=' . lang();

page_head(t('Akaun saya', 'My account'), $u);
echo '<div class="wrap">';
if (isset($_GET['welcome'])) {
    echo flash('good', t('Kata laluan disimpan. Selamat datang!', 'Password saved. Welcome!'));
}
echo '<p class="kicker">' . h($u['company']) . '</p><h1>' . t('Hai', 'Hi') . ', ' . h($u['name'] ?: $u['email']) . '</h1>';

if ($u['tenant_status'] !== 'active') {
    echo '<div style="margin-top:22px">' . flash('bad', t('Akaun syarikat ini telah digantung. Hubungi kami di 011-1632 0694.', 'This company account is suspended. Contact us at 011-1632 0694.')) . '</div>';
} elseif (!$active) {
    echo '<div style="margin-top:22px">' . flash('bad', t('Langganan tamat pada ', 'The subscription ended on ') . fmt_date($u['expires_at']) . '. '
        . t('Data anda kekal selamat. Perbaharui untuk terus menggunakan sistem.', 'Your data is kept safe. Renew to keep using the system.')) . '</div>';
} elseif ($days <= 14 && $u['role'] === 'owner') {
    echo '<div style="margin-top:22px">' . flash('warn', t("Langganan tamat dalam $days hari (", "The subscription ends in $days days (") . fmt_date($u['expires_at']) . ').') . '</div>';
}

if ($active && $u['tenant_status'] === 'active') {
    echo '<div class="grid' . ($isAdmin ? '3' : '2') . ' apps" style="margin-top:26px">';
    echo '<a class="app" href="/app/hr/?' . $lq . '"><b>HR</b><span>' . ($isAdmin
            ? t('Pekerja, kehadiran, cuti, gaji, pengambilan pekerja dan prestasi.', 'People, attendance, leave, payroll, hiring and performance.')
            : t('Daftar masuk dan keluar kerja.', 'Clock in and out of work.')) . '</span></a>';
    if ($isAdmin) {
        echo '<a class="app" href="/app/finance/?' . $lq . '"><b>' . t('Kewangan', 'Finance') . '</b><span>'
            . t('Invois, bil, penyata bank, laporan dan borang LHDN.', 'Invoices, bills, bank statements, reports and LHDN forms.') . '</span></a>'
            . '<a class="app" href="/app/team.php"><b>' . t('Pasukan', 'Team') . '</b><span>'
            . t('Jemput pengurus dan kakitangan untuk log masuk.', 'Invite managers and staff to log in.') . '</span></a>';
    }
    echo '</div>';
}

if ($u['role'] === 'owner') {
    $status = $u['tenant_status'] !== 'active' ? '<span class="status off">' . t('Digantung', 'Suspended') . '</span>'
        : (!$active ? '<span class="status off">' . t('Tamat', 'Ended') . '</span>'
            : ($days <= 14 ? '<span class="status warn">' . t('Hampir tamat', 'Ending soon') . '</span>' : '<span class="status">' . t('Aktif', 'Active') . '</span>'));
    echo '<div class="grid2" style="margin-top:34px"><section class="card"><h2>' . t('Langganan', 'Subscription') . '</h2>'
        . '<p style="margin-bottom:8px">' . $status . '</p>'
        . '<p><b>' . h($plan[lang()]) . '</b></p><p class="muted">' . t('Aktif sehingga', 'Active until') . ' ' . fmt_date($u['expires_at']) . '</p>'
        . '<p class="muted small-text" style="margin:12px 0 16px">' . t('Perbaharui menggunakan e-mel ini', 'Renew using this email') . ' (' . h($u['email']) . ') '
        . t('supaya tempoh ditambah ke akaun ini.', 'so the time is added to this account.') . '</p>'
        . '<div class="row-gap"><a class="btn primary" href="' . h($renew) . '">' . t('Perbaharui', 'Renew') . '</a>'
        . '<a class="btn" href="' . (lang() === 'en' ? '/en/subscribe/' : '/langgan/') . '?plan=' . ($u['plan'] === 'yearly' ? 'quarterly' : 'yearly') . '#form">'
        . ($u['plan'] === 'yearly' ? t('Bayar 3 bulan', 'Pay for 3 months') : t('Tukar ke tahunan', 'Switch to yearly')) . '</a></div></section>';
    $pays = q("SELECT * FROM payments WHERE tenant_id = ? AND status = 'paid' ORDER BY paid_at DESC LIMIT 12", [$u['tenant_id']])->fetchAll();
    echo '<section class="card"><h2>' . t('Bayaran', 'Payments') . '</h2>';
    if (!$pays) {
        echo '<p class="muted">' . t('Tiada bayaran lagi.', 'No payments yet.') . '</p>';
    } else {
        echo '<div class="tbl-wrap"><table class="tbl"><thead><tr><th>' . t('Tarikh', 'Date') . '</th><th>' . t('Pelan', 'Plan') . '</th><th class="num">'
            . t('Jumlah', 'Amount') . '</th></tr></thead><tbody>';
        foreach ($pays as $p) {
            echo '<tr><td>' . fmt_date($p['paid_at']) . '</td><td>' . ($p['plan'] === 'yearly' ? t('Tahunan', 'Yearly') : t('3 bulan', '3 months'))
                . '</td><td class="num">' . rm((int)$p['amount_sen']) . '</td></tr>';
        }
        echo '</tbody></table></div><p class="muted small-text" style="margin-top:10px">' . t('Resit dihantar ke e-mel anda oleh CHIP.', 'CHIP emails your receipts.') . '</p>';
    }
    echo '</section></div>';
}
echo '<p class="muted small-text" style="margin-top:30px">' . t('Perlukan bantuan? WhatsApp 011-1632 0694 atau e-mel work@esrmy.com.', 'Need help? WhatsApp 011-1632 0694 or email work@esrmy.com.') . '</p></div>';
page_foot();
