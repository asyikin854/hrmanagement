<?php
// Owner dashboard: subscribers, payments, revenue and AI usage, with actions to extend, suspend, add a
// subscriber by hand (e.g. bank transfer), re-check a payment with CHIP and export CSV.
declare(strict_types=1);
require __DIR__ . '/../app/lib/layout.php';
require __DIR__ . '/../app/lib/billing.php';

$me = current_user();
if (!$me) {
    header('Location: /app/login.php?next=' . rawurlencode('/admin/'), true, 303);
    exit;
}
if ($me['role'] !== 'superadmin') {
    header('Location: /app/', true, 303);
    exit;
}

$view = (string)($_GET['view'] ?? 'overview');
$msg = '';
$link = '';

/* ---------------- data ---------------- */

function tenants_with_owner(string $where = '1=1', array $args = []): array
{
    return q("SELECT t.*, u.id AS owner_id, u.name AS owner_name, u.email AS owner_email, u.password_hash IS NOT NULL AS owner_ready,
                (SELECT COALESCE(SUM(p.amount_sen), 0) FROM payments p WHERE p.tenant_id = t.id AND p.status = 'paid') AS paid_total,
                (SELECT COUNT(*) FROM users x WHERE x.tenant_id = t.id AND x.active = 1) AS users_count,
                (SELECT MAX(x.last_login_at) FROM users x WHERE x.tenant_id = t.id) AS last_seen
              FROM tenants t LEFT JOIN users u ON u.tenant_id = t.id AND u.role = 'owner'
              WHERE $where ORDER BY t.expires_at ASC", $args)->fetchAll();
}

function tenant_state(array $t): string
{
    if ($t['status'] !== 'active') {
        return 'suspended';
    }
    $left = strtotime($t['expires_at']) - time();
    return $left <= 0 ? 'expired' : ($left <= 30 * 86400 ? 'expiring' : 'active');
}

function state_badge(string $s): string
{
    $map = [
        'active' => ['', t('Aktif', 'Active')],
        'expiring' => ['warn', t('Hampir tamat', 'Ending soon')],
        'expired' => ['off', t('Tamat', 'Expired')],
        'suspended' => ['off', t('Digantung', 'Suspended')],
    ];
    return '<span class="status ' . $map[$s][0] . '">' . $map[$s][1] . '</span>';
}

function plan_name(string $p): string
{
    return $p === 'yearly' ? t('Tahunan', 'Yearly') : t('3 bulan', '3 months');
}

function pay_status(string $s): string
{
    $m = ['paid' => ['', t('Dibayar', 'Paid')], 'created' => ['warn', t('Menunggu', 'Pending')],
        'failed' => ['off', t('Gagal / batal', 'Failed / cancelled')], 'review' => ['off', t('Perlu semakan', 'Needs review')]];
    return '<span class="status ' . ($m[$s][0] ?? 'warn') . '">' . ($m[$s][1] ?? h($s)) . '</span>';
}

/* ---------------- CSV export ---------------- */

if (isset($_GET['export'])) {
    $kind = $_GET['export'] === 'payments' ? 'payments' : 'subscribers';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="esr-' . $kind . '-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // so Excel reads UTF-8
    $cell = fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v; // no formulas from customer input
    if ($kind === 'payments') {
        fputcsv($out, ['paid_at', 'created_at', 'status', 'kind', 'company', 'name', 'email', 'phone', 'plan', 'amount_rm', 'period_from', 'period_to', 'chip_purchase_id', 'note']);
        foreach (q('SELECT * FROM payments ORDER BY created_at DESC') as $p) {
            fputcsv($out, array_map($cell, [$p['paid_at'], $p['created_at'], $p['status'], $p['kind'], $p['company'], $p['name'], $p['email'], $p['phone'], $p['plan'],
                number_format($p['amount_sen'] / 100, 2, '.', ''), $p['period_from'], $p['period_to'], $p['purchase_id'], $p['note']]));
        }
    } else {
        fputcsv($out, ['company', 'owner', 'email', 'phone', 'plan', 'state', 'started', 'expires', 'paid_total_rm', 'users', 'last_login', 'notes']);
        foreach (tenants_with_owner() as $t) {
            fputcsv($out, array_map($cell, [$t['company'], $t['owner_name'], $t['owner_email'], $t['phone'], $t['plan'], tenant_state($t), $t['starts_at'], $t['expires_at'],
                number_format($t['paid_total'] / 100, 2, '.', ''), $t['users_count'], $t['last_seen'], $t['notes']]));
        }
    }
    exit;
}

/* ---------------- actions ---------------- */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    check_csrf();
    $act = (string)($_POST['act'] ?? '');
    $tid = (int)($_POST['tenant'] ?? 0);
    $t = $tid ? q('SELECT * FROM tenants WHERE id = ?', [$tid])->fetch() : null;
    try {
        if ($act === 'extend' && $t) {
            $months = max(0, min(36, (int)($_POST['months'] ?? 0)));
            $date = (string)($_POST['until'] ?? '');
            $amount = (int)round((float)($_POST['amount'] ?? 0) * 100);
            $from = strtotime($t['expires_at']) > time() ? $t['expires_at'] : now();
            $to = $months ? add_months($from, $months) : (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date . ' 23:59:59' : null);
            if (!$to) {
                throw new RuntimeException(t('Pilih bilangan bulan atau tarikh.', 'Choose a number of months or a date.'));
            }
            q("UPDATE tenants SET expires_at = ?, status = 'active', reminded_for = NULL WHERE id = ?", [$to, $tid]);
            if ($amount > 0) {
                $o = q("SELECT * FROM users WHERE tenant_id = ? AND role = 'owner'", [$tid])->fetch();
                q("INSERT INTO payments (tenant_id, email, name, company, phone, plan, amount_sen, status, kind, lang, period_from, period_to, note, created_at, paid_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, 'paid', 'renewal', ?, ?, ?, ?, ?, ?)",
                    [$tid, $o['email'] ?? '', $o['name'] ?? '', $t['company'], $t['phone'], $t['plan'], $amount, $t['lang'], $from, $to,
                        mb_substr('Manual: ' . trim((string)($_POST['note'] ?? '')), 0, 255), now(), now()]);
            }
            $msg = flash('good', t('Langganan dikemas kini sehingga ', 'Subscription updated until ') . fmt_date($to) . '.');
        } elseif (($act === 'suspend' || $act === 'reactivate') && $t) {
            q('UPDATE tenants SET status = ? WHERE id = ?', [$act === 'suspend' ? 'suspended' : 'active', $tid]);
            $msg = flash('good', $act === 'suspend' ? t('Akaun digantung. Data disimpan.', 'Account suspended. Data is kept.') : t('Akaun diaktifkan semula.', 'Account reactivated.'));
        } elseif ($act === 'save' && $t) {
            q('UPDATE tenants SET company = ?, phone = ?, notes = ? WHERE id = ?', [mb_substr(trim((string)$_POST['company']), 0, 160) ?: $t['company'],
                mb_substr(trim((string)$_POST['phone']), 0, 32), mb_substr((string)$_POST['notes'], 0, 5000), $tid]);
            $msg = flash('good', t('Disimpan.', 'Saved.'));
        } elseif ($act === 'owner_link' && $t) {
            $o = q("SELECT * FROM users WHERE tenant_id = ? AND role = 'owner'", [$tid])->fetch();
            if ($o) {
                $link = make_password_link((int)$o['id'], 7);
                if (!empty($_POST['send'])) {
                    $ok = send_mail($o['email'], t('Tetapkan kata laluan anda', 'Set your password'), t(
                        "Hai {$o['name']},\n\nTetapkan kata laluan untuk akaun {$t['company']} di sini (sah 7 hari):\n$link\n\nExcellent Synergy Resources",
                        "Hi {$o['name']},\n\nSet the password for the {$t['company']} account here (works for 7 days):\n$link\n\nExcellent Synergy Resources"));
                    $msg = flash($ok ? 'good' : 'bad', $ok ? t('E-mel dihantar.', 'Email sent.') : t('E-mel gagal. Salin pautan di bawah.', "The email failed. Copy the link below."));
                }
            }
        } elseif ($act === 'check_chip') {
            $p = q('SELECT * FROM payments WHERE id = ?', [(int)($_POST['payment'] ?? 0)])->fetch();
            $r = $p && $p['purchase_id'] ? process_purchase($p['purchase_id']) : 'unknown';
            $labels = ['paid-new' => t('Dibayar: akaun baru dicipta.', 'Paid: new account created.'), 'paid-renewal' => t('Dibayar: langganan dilanjutkan.', 'Paid: subscription extended.'),
                'already' => t('Sudah diproses.', 'Already processed.'), 'not-paid' => t('CHIP: belum dibayar.', 'CHIP: not paid.'),
                'review' => t('Perlu semakan (lihat nota).', 'Needs review (see the note).'), 'unknown' => t('Tidak ditemui di CHIP.', 'Not found at CHIP.')];
            $msg = flash(str_starts_with($r, 'paid') ? 'good' : 'warn', $labels[$r] ?? h($r));
        } elseif ($act === 'add') {
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $plan = ($_POST['plan'] ?? '') === 'quarterly' ? 'quarterly' : 'yearly';
            $company = trim((string)($_POST['company'] ?? ''));
            $name = trim((string)($_POST['name'] ?? ''));
            if ($company === '' || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException(t('Isi syarikat, nama dan e-mel yang sah.', 'Add the company, name and a valid email.'));
            }
            if (q('SELECT id FROM users WHERE email = ?', [$email])->fetch()) {
                throw new RuntimeException(t('E-mel ini sudah mempunyai akaun. Gunakan "Lanjutkan" pada pelanggan itu.', 'This email already has an account. Use "Extend" on that subscriber.'));
            }
            $amount = (int)round((float)($_POST['amount'] ?? 0) * 100);
            q("INSERT INTO payments (email, name, company, phone, plan, amount_sen, status, lang, note, created_at) VALUES (?, ?, ?, ?, ?, ?, 'created', ?, ?, ?)",
                [$email, $name, $company, mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 32), $plan, $amount, ($_POST['lang'] ?? '') === 'en' ? 'en' : 'ms',
                    mb_substr('Manual: ' . trim((string)($_POST['note'] ?? '')), 0, 255), now()]);
            $pay = q('SELECT * FROM payments WHERE id = ?', [(int)db()->lastInsertId()])->fetch();
            $r = provision($pay, 'manual');
            $msg = flash('good', h($company) . ' ' . t('ditambah dan e-mel aluan dihantar.', 'added and the welcome email sent.'));
            $view = 'subscribers';
        }
    } catch (Throwable $e) {
        $msg = flash('bad', h($e instanceof RuntimeException ? $e->getMessage() : t('Ralat: ', 'Error: ') . $e->getMessage()));
    }
}

/* ---------------- page ---------------- */

page_head(t('Papan pemuka', 'Dashboard'), $me, '/admin/');
$tabs = ['overview' => t('Ringkasan', 'Overview'), 'subscribers' => t('Pelanggan', 'Subscribers'), 'payments' => t('Bayaran', 'Payments'), 'ai' => t('Penggunaan AI', 'AI usage')];
echo '<div class="wrap"><div class="admin-head"><h1>' . t('Papan pemuka', 'Dashboard') . '</h1><nav class="tabs" aria-label="' . t('Bahagian', 'Sections') . '">';
foreach ($tabs as $k => $label) {
    echo '<a href="/admin/?view=' . $k . '"' . ($view === $k || ($view === 'tenant' && $k === 'subscribers') ? ' aria-current="page"' : '') . '>' . $label . '</a>';
}
echo '</nav></div>' . $msg;
if ($link) {
    echo '<div class="card" style="margin-bottom:20px"><p class="small-text muted" style="margin-bottom:8px">' . t('Pautan tetapkan kata laluan (sah 7 hari):', 'Set-password link (works for 7 days):')
        . '</p><div class="copy"><input readonly value="' . h($link) . '" onclick="this.select()"><button class="btn small" type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value);this.textContent=\''
        . t('Disalin', 'Copied') . '\'">' . t('Salin', 'Copy') . '</button></div></div>';
}

if ($view === 'overview') {
    $all = tenants_with_owner();
    $counts = ['active' => 0, 'expiring' => 0, 'expired' => 0, 'suspended' => 0];
    $plans = ['yearly' => 0, 'quarterly' => 0];
    foreach ($all as $t) {
        $s = tenant_state($t);
        $counts[$s]++;
        if ($s === 'active' || $s === 'expiring') {
            $plans[$t['plan']] = ($plans[$t['plan']] ?? 0) + 1;
        }
    }
    $live = $counts['active'] + $counts['expiring'];
    $monthStart = date('Y-m-01 00:00:00');
    $thisMonth = (int)q("SELECT COALESCE(SUM(amount_sen), 0) FROM payments WHERE status = 'paid' AND paid_at >= ?", [$monthStart])->fetchColumn();
    $lastMonth = (int)q("SELECT COALESCE(SUM(amount_sen), 0) FROM payments WHERE status = 'paid' AND paid_at >= ? AND paid_at < ?",
        [date('Y-m-01 00:00:00', strtotime('first day of last month')), $monthStart])->fetchColumn();
    $newThisMonth = (int)q("SELECT COUNT(*) FROM payments WHERE status = 'paid' AND kind = 'new' AND paid_at >= ?", [$monthStart])->fetchColumn();
    $attention = (int)q("SELECT COUNT(*) FROM payments WHERE status = 'review' OR (status = 'created' AND created_at < ? AND created_at > ?)",
        [date('Y-m-d H:i:s', time() - 3600), date('Y-m-d H:i:s', time() - 7 * 86400)])->fetchColumn();
    // Annualised recurring revenue from live subscriptions at their current plan price
    $arr = $plans['yearly'] * PLANS['yearly']['price'] + $plans['quarterly'] * PLANS['quarterly']['price'] * 4;

    echo '<div class="kpis">'
        . '<div class="kpi"><span>' . t('Pelanggan aktif', 'Active subscribers') . '</span><b>' . $live . '</b><small>' . ($newThisMonth ? '+' . $newThisMonth . ' ' . t('baru bulan ini', 'new this month') : t('Tiada pelanggan baru bulan ini', 'No new subscribers this month')) . '</small></div>'
        . '<div class="kpi"><span>' . t('Hasil bulan ini', 'Revenue this month') . '</span><b>' . rm($thisMonth) . '</b><small>' . t('Bulan lepas', 'Last month') . ': ' . rm($lastMonth) . '</small></div>'
        . '<div class="kpi"><span>' . t('Hasil tahunan berulang', 'Annual recurring revenue') . '</span><b>' . rm($arr) . '</b><small>' . t('Pelanggan aktif × harga pelan setahun', 'Live subscribers × yearly plan value') . '</small></div>'
        . '<div class="kpi"><span>' . t('Tamat dalam 30 hari', 'Ending in 30 days') . '</span><b>' . $counts['expiring'] . '</b><small>' . t('Tamat dan belum diperbaharui', 'Expired, not renewed') . ': ' . $counts['expired'] . '</small></div>'
        . '<div class="kpi"><span>' . t('Campuran pelan', 'Plan mix') . '</span><b class="kpi-pair">' . $plans['yearly'] . ' <em>' . t('tahunan', 'yearly') . '</em> · ' . $plans['quarterly'] . ' <em>' . t('3 bulan', '3-month') . '</em></b><small>' . t('Pelanggan aktif sahaja', 'Live subscribers only') . '</small></div>'
        . '<div class="kpi' . ($attention ? ' alert' : '') . '"><span>' . t('Bayaran perlu dilihat', 'Payments to check') . '</span><b>' . $attention . '</b><small>' . ($attention ? '<a href="/admin/?view=payments&amp;status=attention">' . t('Lihat', 'View') . '</a>' : t('Semua beres', 'All clear')) . '</small></div>'
        . '</div>';

    // Revenue by month, last 12 months, split by new subscribers and renewals
    $months = [];
    for ($i = 11; $i >= 0; $i--) {
        $months[date('Y-m', strtotime(date('Y-m-01') . " -$i month"))] = ['new' => 0, 'renewal' => 0];
    }
    foreach (q("SELECT DATE_FORMAT(paid_at, '%Y-%m') AS m, kind, SUM(amount_sen) AS s FROM payments WHERE status = 'paid' AND paid_at >= ? GROUP BY m, kind",
        [array_key_first($months) . '-01 00:00:00']) as $r) {
        if (isset($months[$r['m']])) {
            $months[$r['m']][$r['kind'] === 'renewal' ? 'renewal' : 'new'] += (int)$r['s'];
        }
    }
    echo '<section class="card chart-card"><div class="chart-head"><h2>' . t('Hasil mengikut bulan', 'Revenue by month') . '</h2>'
        . '<div class="legend"><span><i class="sw sw-new"></i>' . t('Pelanggan baru', 'New subscribers') . '</span><span><i class="sw sw-renew"></i>' . t('Pembaharuan', 'Renewals') . '</span></div></div>'
        . '<div class="chart-scroll">' . revenue_chart($months) . '</div>'
        . '<details class="small-text"><summary>' . t('Lihat sebagai jadual', 'View as a table') . '</summary><div class="tbl-wrap" style="margin-top:10px"><table class="tbl"><thead><tr><th>' . t('Bulan', 'Month') . '</th><th class="num">'
        . t('Baru', 'New') . '</th><th class="num">' . t('Pembaharuan', 'Renewals') . '</th><th class="num">' . t('Jumlah', 'Total') . '</th></tr></thead><tbody>';
    foreach (array_reverse($months, true) as $m => $v) {
        echo '<tr><td>' . month_label($m, true) . '</td><td class="num">' . rm($v['new']) . '</td><td class="num">' . rm($v['renewal']) . '</td><td class="num">' . rm($v['new'] + $v['renewal']) . '</td></tr>';
    }
    echo '</tbody></table></div></details></section>';

    $soon = array_filter($all, fn($t) => tenant_state($t) === 'expiring');
    echo '<div class="grid2" style="margin-top:22px"><section><h2>' . t('Akan tamat (30 hari)', 'Ending in the next 30 days') . '</h2>' . tenant_table(array_slice($soon, 0, 15), true) . '</section>';
    $recent = q("SELECT * FROM payments WHERE status = 'paid' ORDER BY paid_at DESC LIMIT 10")->fetchAll();
    echo '<section><h2>' . t('Bayaran terkini', 'Latest payments') . '</h2>' . payment_table($recent, true) . '</section></div>';
} elseif ($view === 'subscribers') {
    $qstr = trim((string)($_GET['q'] ?? ''));
    $st = (string)($_GET['state'] ?? '');
    $rows = $qstr === '' ? tenants_with_owner()
        : tenants_with_owner('(t.company LIKE ? OR u.email LIKE ? OR u.name LIKE ? OR t.phone LIKE ?)', array_fill(0, 4, '%' . $qstr . '%'));
    if ($st !== '') {
        $rows = array_values(array_filter($rows, fn($t) => tenant_state($t) === $st));
    }
    echo '<form class="filters" method="get"><input type="hidden" name="view" value="subscribers"><label class="field">' . t('Cari', 'Search')
        . '<input name="q" value="' . h($qstr) . '" placeholder="' . t('Syarikat, nama, e-mel, telefon', 'Company, name, email, phone') . '"></label><label class="field">' . t('Status', 'Status') . '<select name="state">'
        . '<option value="">' . t('Semua', 'All') . '</option>';
    foreach (['active' => t('Aktif', 'Active'), 'expiring' => t('Hampir tamat', 'Ending soon'), 'expired' => t('Tamat', 'Expired'), 'suspended' => t('Digantung', 'Suspended')] as $k => $v) {
        echo '<option value="' . $k . '"' . ($st === $k ? ' selected' : '') . '>' . $v . '</option>';
    }
    echo '</select></label><button class="btn" type="submit">' . t('Tapis', 'Filter') . '</button><a class="btn" href="/admin/?export=subscribers">' . t('Muat turun CSV', 'Download CSV') . '</a></form>';
    echo '<p class="muted small-text" style="margin:6px 0 10px">' . count($rows) . ' ' . t('pelanggan', 'subscribers') . '</p>' . tenant_table($rows, false);
    echo '<details class="card" style="margin-top:26px"><summary><b>' . t('Tambah pelanggan secara manual', 'Add a subscriber by hand') . '</b> <span class="muted small-text">'
        . t('(contoh: bayar melalui pindahan bank)', '(for example, paid by bank transfer)') . '</span></summary><form method="post" class="stack" style="margin-top:16px">' . csrf_field()
        . '<input type="hidden" name="act" value="add"><div class="fields">'
        . '<label class="field">' . t('Syarikat', 'Company') . '<input name="company" required></label><label class="field">' . t('Nama', 'Name') . '<input name="name" required></label>'
        . '<label class="field">' . t('E-mel', 'Email') . '<input type="email" name="email" required></label><label class="field">' . t('Telefon', 'Phone') . '<input name="phone"></label>'
        . '<label class="field">' . t('Pelan', 'Plan') . '<select name="plan"><option value="yearly">' . t('Tahunan', 'Yearly') . ' (RM1,999)</option><option value="quarterly">' . t('3 bulan', '3 months') . ' (RM550)</option></select></label>'
        . '<label class="field">' . t('Jumlah diterima (RM)', 'Amount received (RM)') . '<input type="number" name="amount" min="0" step="0.01" value="0"></label>'
        . '<label class="field">' . t('Bahasa e-mel', 'Email language') . '<select name="lang"><option value="ms">BM</option><option value="en">English</option></select></label>'
        . '<label class="field">' . t('Nota', 'Note') . '<input name="note" maxlength="200"></label></div>'
        . '<button class="btn primary" type="submit">' . t('Tambah dan hantar e-mel aluan', 'Add and send the welcome email') . '</button></form></details>';
} elseif ($view === 'tenant') {
    $tid = (int)($_GET['id'] ?? 0);
    $t = tenants_with_owner('t.id = ?', [$tid])[0] ?? null;
    if (!$t) {
        echo flash('bad', t('Pelanggan tidak ditemui.', 'Subscriber not found.'));
    } else {
        $users = q('SELECT * FROM users WHERE tenant_id = ? ORDER BY role, name', [$tid])->fetchAll();
        $pays = q('SELECT * FROM payments WHERE tenant_id = ? ORDER BY created_at DESC', [$tid])->fetchAll();
        $ai = q('SELECT * FROM ai_usage WHERE tenant_id = ? ORDER BY month DESC LIMIT 6', [$tid])->fetchAll();
        $docs = q("SELECT app, COUNT(*) AS n FROM docs WHERE tenant_id = ? AND deleted = 0 GROUP BY app", [$tid])->fetchAll(PDO::FETCH_KEY_PAIR);
        $hidden = csrf_field() . '<input type="hidden" name="tenant" value="' . $tid . '">';
        echo '<p><a href="/admin/?view=subscribers">← ' . t('Pelanggan', 'Subscribers') . '</a></p><div class="row-gap" style="margin:10px 0 20px"><h2 style="margin:0">' . h($t['company']) . '</h2>' . state_badge(tenant_state($t)) . '</div>';
        echo '<div class="grid2"><section class="card"><h3>' . t('Langganan', 'Subscription') . '</h3><dl class="facts">'
            . '<dt>' . t('Pelan', 'Plan') . '</dt><dd>' . plan_name($t['plan']) . '</dd><dt>' . t('Mula', 'Started') . '</dt><dd>' . fmt_date($t['starts_at']) . '</dd>'
            . '<dt>' . t('Tamat', 'Ends') . '</dt><dd>' . fmt_date($t['expires_at']) . '</dd><dt>' . t('Jumlah dibayar', 'Total paid') . '</dt><dd>' . rm((int)$t['paid_total']) . '</dd>'
            . '<dt>' . t('Pemilik', 'Owner') . '</dt><dd>' . h($t['owner_name']) . '<br><a href="mailto:' . h($t['owner_email']) . '">' . h($t['owner_email']) . '</a>'
            . ($t['owner_ready'] ? '' : '<br><span class="status warn">' . t('Belum tetapkan kata laluan', 'Password not set yet') . '</span>') . '</dd>'
            . '<dt>' . t('Telefon', 'Phone') . '</dt><dd>' . ($t['phone'] ? '<a href="https://wa.me/' . h(preg_replace('/\D/', '', $t['phone'])) . '" target="_blank" rel="noopener">' . h($t['phone']) . '</a>' : '-') . '</dd>'
            . '<dt>' . t('Data', 'Data') . '</dt><dd>HR: ' . (int)($docs['hr'] ?? 0) . ' · ' . t('Kewangan', 'Finance') . ': ' . (int)($docs['fin'] ?? 0) . ' ' . t('rekod', 'records') . '</dd></dl>'
            . '<div class="row-gap" style="margin-top:16px"><form method="post">' . $hidden . '<input type="hidden" name="act" value="owner_link"><input type="hidden" name="send" value="1"><button class="btn small" type="submit">'
            . t('E-mel pautan kata laluan', 'Email a password link') . '</button></form>'
            . '<form method="post">' . $hidden . '<input type="hidden" name="act" value="owner_link"><button class="btn small" type="submit">' . t('Dapatkan pautan', 'Get the link') . '</button></form>'
            . '<form method="post" onsubmit="return confirm(\'' . ($t['status'] === 'active' ? t('Gantung akaun ini? Mereka tidak boleh log masuk sehingga diaktifkan semula.', 'Suspend this account? They cannot use it until reactivated.') : t('Aktifkan semula?', 'Reactivate?')) . '\')">'
            . $hidden . '<input type="hidden" name="act" value="' . ($t['status'] === 'active' ? 'suspend' : 'reactivate') . '"><button class="btn small" type="submit">'
            . ($t['status'] === 'active' ? t('Gantung', 'Suspend') : t('Aktifkan semula', 'Reactivate')) . '</button></form></div></section>';
        echo '<section class="card"><h3>' . t('Lanjutkan atau tukar tarikh tamat', 'Extend or change the end date') . '</h3><form method="post" class="stack" style="margin-top:12px">' . $hidden
            . '<input type="hidden" name="act" value="extend"><div class="fields"><label class="field">' . t('Tambah bulan', 'Add months') . '<select name="months"><option value="0">-</option><option value="1">1</option><option value="3">3</option><option value="6">6</option><option value="12">12</option></select></label>'
            . '<label class="field">' . t('atau tetapkan tarikh tamat', 'or set the end date') . '<input type="date" name="until"></label>'
            . '<label class="field">' . t('Bayaran diterima (RM, jika ada)', 'Payment received (RM, if any)') . '<input type="number" name="amount" min="0" step="0.01" value="0"></label>'
            . '<label class="field">' . t('Nota', 'Note') . '<input name="note" maxlength="200"></label></div><button class="btn primary" type="submit">' . t('Simpan', 'Save') . '</button></form>'
            . '<form method="post" class="stack" style="margin-top:22px">' . $hidden . '<input type="hidden" name="act" value="save"><div class="fields">'
            . '<label class="field">' . t('Syarikat', 'Company') . '<input name="company" value="' . h($t['company']) . '"></label><label class="field">' . t('Telefon', 'Phone') . '<input name="phone" value="' . h($t['phone']) . '"></label>'
            . '<label class="field full">' . t('Nota dalaman', 'Internal notes') . '<textarea name="notes" rows="3">' . h($t['notes']) . '</textarea></label></div><button class="btn" type="submit">' . t('Simpan butiran', 'Save details') . '</button></form></section></div>';
        echo '<h3 style="margin-top:28px">' . t('Pengguna', 'Users') . '</h3><div class="tbl-wrap"><table class="tbl"><thead><tr><th>' . t('Nama', 'Name') . '</th><th>' . t('E-mel', 'Email') . '</th><th>' . t('Peranan', 'Role') . '</th><th>' . t('Log masuk terakhir', 'Last log in') . '</th></tr></thead><tbody>';
        foreach ($users as $x) {
            echo '<tr' . ($x['active'] ? '' : ' class="muted"') . '><td>' . h($x['name']) . '</td><td>' . h($x['email']) . '</td><td>' . h($x['role']) . ($x['active'] ? '' : ' · ' . t('dibuang', 'removed')) . '</td><td>' . ($x['last_login_at'] ? fmt_date($x['last_login_at']) : '-') . '</td></tr>';
        }
        echo '</tbody></table></div><h3 style="margin-top:28px">' . t('Bayaran', 'Payments') . '</h3>' . payment_table($pays, false);
        echo '<h3 style="margin-top:28px">' . t('Penggunaan AI', 'AI usage') . '</h3>' . ai_table($ai, false);
    }
} elseif ($view === 'payments') {
    $st = (string)($_GET['status'] ?? '');
    $where = match ($st) {
        'paid', 'created', 'failed', 'review' => ['status = ?', [$st]],
        'attention' => ["status = 'review' OR (status = 'created' AND created_at < ?)", [date('Y-m-d H:i:s', time() - 3600)]],
        default => ['1=1', []],
    };
    $rows = q('SELECT * FROM payments WHERE ' . $where[0] . ' ORDER BY created_at DESC LIMIT 500', $where[1])->fetchAll();
    $sum = array_sum(array_map(fn($p) => $p['status'] === 'paid' ? (int)$p['amount_sen'] : 0, $rows));
    echo '<form class="filters" method="get"><input type="hidden" name="view" value="payments"><label class="field">' . t('Status', 'Status') . '<select name="status"><option value="">' . t('Semua', 'All') . '</option>';
    foreach (['paid' => t('Dibayar', 'Paid'), 'created' => t('Menunggu', 'Pending'), 'failed' => t('Gagal / batal', 'Failed / cancelled'), 'review' => t('Perlu semakan', 'Needs review'), 'attention' => t('Perlu dilihat', 'To check')] as $k => $v) {
        echo '<option value="' . $k . '"' . ($st === $k ? ' selected' : '') . '>' . $v . '</option>';
    }
    echo '</select></label><button class="btn" type="submit">' . t('Tapis', 'Filter') . '</button><a class="btn" href="/admin/?export=payments">' . t('Muat turun CSV', 'Download CSV') . '</a></form>'
        . '<p class="muted small-text" style="margin:6px 0 10px">' . count($rows) . ' ' . t('rekod', 'records') . ' · ' . t('dibayar', 'paid') . ' ' . rm($sum) . '</p>'
        . '<p class="muted small-text" style="margin-bottom:12px">' . t('"Menunggu" bermaksud pelanggan membuka halaman bayaran tetapi CHIP belum mengesahkan bayaran. Gunakan "Semak dengan CHIP" jika anda pasti mereka sudah membayar.',
            '"Pending" means the customer opened the payment page but CHIP hasn\'t confirmed a payment. Use "Check with CHIP" if you know they paid.') . '</p>'
        . payment_table($rows, false, true);
} elseif ($view === 'ai') {
    $rows = q('SELECT a.*, t.company FROM ai_usage a JOIN tenants t ON t.id = a.tenant_id ORDER BY a.month DESC, a.requests DESC LIMIT 300')->fetchAll();
    echo '<p class="muted" style="margin-bottom:14px">' . t('Had setiap syarikat', 'Limit per company') . ': ' . (int)cfg('ai_monthly_limit', 300) . ' ' . t('permintaan sebulan (ai_monthly_limit dalam esr-config.php). Kos ialah anggaran berdasarkan harga model dalam USD; bil sebenar ada di Claude Console.',
        'requests a month (ai_monthly_limit in esr-config.php). Cost is an estimate from the model\'s USD prices; the actual bill is in the Claude Console.') . '</p>' . ai_table($rows, true);
}
echo '</div>';
page_foot();

/* ---------------- building blocks ---------------- */

function tenant_table(array $rows, bool $short): string
{
    if (!$rows) {
        return '<p class="muted">' . t('Tiada.', 'None.') . '</p>';
    }
    $h = '<div class="tbl-wrap"><table class="tbl"><thead><tr><th>' . t('Syarikat', 'Company') . '</th>' . ($short ? '' : '<th>' . t('Pemilik', 'Owner') . '</th><th>' . t('Pelan', 'Plan') . '</th>')
        . '<th>' . t('Status', 'Status') . '</th><th>' . t('Tamat', 'Ends') . '</th>' . ($short ? '' : '<th class="num">' . t('Dibayar', 'Paid') . '</th><th class="num">' . t('Pengguna', 'Users') . '</th><th>' . t('Aktiviti terakhir', 'Last active') . '</th>') . '</tr></thead><tbody>';
    foreach ($rows as $t) {
        $h .= '<tr><td><a href="/admin/?view=tenant&amp;id=' . (int)$t['id'] . '">' . h($t['company']) . '</a></td>'
            . ($short ? '' : '<td>' . h($t['owner_name']) . '<br><span class="muted small-text">' . h($t['owner_email']) . '</span></td><td>' . plan_name($t['plan']) . '</td>')
            . '<td>' . state_badge(tenant_state($t)) . '</td><td>' . fmt_date($t['expires_at']) . '</td>'
            . ($short ? '' : '<td class="num">' . rm((int)$t['paid_total']) . '</td><td class="num">' . (int)$t['users_count'] . '</td><td>' . ($t['last_seen'] ? fmt_date($t['last_seen']) : t('Belum', 'Not yet')) . '</td>') . '</tr>';
    }
    return $h . '</tbody></table></div>';
}

function payment_table(array $rows, bool $short, bool $actions = false): string
{
    if (!$rows) {
        return '<p class="muted">' . t('Tiada.', 'None.') . '</p>';
    }
    $h = '<div class="tbl-wrap"><table class="tbl"><thead><tr><th>' . t('Tarikh', 'Date') . '</th><th>' . t('Syarikat', 'Company') . '</th>' . ($short ? '' : '<th>' . t('Pelan', 'Plan') . '</th><th>' . t('Status', 'Status') . '</th>')
        . '<th class="num">' . t('Jumlah', 'Amount') . '</th>' . ($short ? '' : '<th>' . t('Butiran', 'Details') . '</th>') . '</tr></thead><tbody>';
    foreach ($rows as $p) {
        $company = $p['tenant_id'] ? '<a href="/admin/?view=tenant&amp;id=' . (int)$p['tenant_id'] . '">' . h($p['company']) . '</a>' : h($p['company']);
        $details = '';
        if (!$short) {
            $details = h($p['email']) . ($p['kind'] ? ' · ' . ($p['kind'] === 'new' ? t('baru', 'new') : t('pembaharuan', 'renewal')) : '') . ($p['note'] ? '<br><span class="muted small-text">' . h($p['note']) . '</span>' : '')
                . ($p['purchase_id'] ? '<br><span class="muted small-text">CHIP ' . h(substr($p['purchase_id'], 0, 8)) . '…</span>' : '');
            if ($actions && $p['purchase_id'] && in_array($p['status'], ['created', 'failed'], true)) {
                $details .= '<br><form method="post">' . csrf_field() . '<input type="hidden" name="act" value="check_chip"><input type="hidden" name="payment" value="' . (int)$p['id'] . '"><button class="linkbtn" type="submit">'
                    . t('Semak dengan CHIP', 'Check with CHIP') . '</button></form>';
            }
        }
        $h .= '<tr><td>' . fmt_date($p['paid_at'] ?: $p['created_at']) . '</td><td>' . $company . '</td>' . ($short ? '' : '<td>' . plan_name($p['plan']) . '</td><td>' . pay_status($p['status']) . '</td>')
            . '<td class="num">' . rm((int)$p['amount_sen']) . '</td>' . ($short ? '' : '<td>' . $details . '</td>') . '</tr>';
    }
    return $h . '</tbody></table></div>';
}

function ai_table(array $rows, bool $withCompany): string
{
    if (!$rows) {
        return '<p class="muted">' . t('Belum ada penggunaan AI.', 'No AI use yet.') . '</p>';
    }
    $pin = (float)cfg('ai_price_in', 4.0);
    $pout = (float)cfg('ai_price_out', 20.0);
    $h = '<div class="tbl-wrap"><table class="tbl"><thead><tr><th>' . t('Bulan', 'Month') . '</th>' . ($withCompany ? '<th>' . t('Syarikat', 'Company') . '</th>' : '')
        . '<th class="num">' . t('Permintaan', 'Requests') . '</th><th class="num">' . t('Token masuk', 'Input tokens') . '</th><th class="num">' . t('Token keluar', 'Output tokens') . '</th><th class="num">' . t('Anggaran kos', 'Est. cost') . '</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $cost = $r['input_tokens'] / 1e6 * $pin + $r['output_tokens'] / 1e6 * $pout;
        $h .= '<tr><td>' . month_label($r['month'], true) . '</td>' . ($withCompany ? '<td><a href="/admin/?view=tenant&amp;id=' . (int)$r['tenant_id'] . '">' . h($r['company']) . '</a></td>' : '')
            . '<td class="num">' . number_format((int)$r['requests']) . '</td><td class="num">' . number_format((int)$r['input_tokens']) . '</td><td class="num">' . number_format((int)$r['output_tokens'])
            . '</td><td class="num">US$' . number_format($cost, 2) . '</td></tr>';
    }
    return $h . '</tbody></table></div>';
}

function month_label(string $ym, bool $withYear = false): string
{
    $m = lang() === 'en' ? ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] : ['Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun', 'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis'];
    return $m[(int)substr($ym, 5, 2) - 1] . ($withYear ? ' ' . substr($ym, 0, 4) : '');
}

// Stacked monthly bars (new at the base, renewals on top), drawn as inline SVG. Hovering a month shows its numbers.
function revenue_chart(array $months): string
{
    $W = 760;
    $H = 260;
    $pl = 56;
    $pr = 8;
    $pt = 12;
    $pb = 30;
    $max = max(1, ...array_values(array_map(fn($v) => $v["new"] + $v["renewal"], $months)));
    $raw = $max / 4;
    $mag = 10 ** floor(log10($raw));
    $norm = $raw / $mag;
    $step = (int)(($norm <= 1 ? 1 : ($norm <= 2 ? 2 : ($norm <= 5 ? 5 : 10))) * $mag);
    $step = max($step, 10000);
    $top = $step * (int)ceil($max / $step);
    $y = fn($v) => $pt + ($H - $pt - $pb) * (1 - $v / $top);
    $n = count($months);
    $cw = ($W - $pl - $pr) / $n;
    $bw = min(34, $cw * 0.56);
    $svg = '<svg class="rev-chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . h(t('Hasil mengikut bulan, 12 bulan terakhir', 'Revenue by month, last 12 months')) . '">';
    for ($v = 0; $v <= $top; $v += $step) {
        $yy = round($y($v), 1);
        $svg .= '<line class="grid" x1="' . $pl . '" x2="' . ($W - $pr) . '" y1="' . $yy . '" y2="' . $yy . '"/>'
            . '<text class="axis" x="' . ($pl - 8) . '" y="' . ($yy + 4) . '" text-anchor="end">' . ($v >= 100000 ? 'RM' . rtrim(rtrim(number_format($v / 100000, 1), '0'), '.') . 'k' : 'RM' . number_format($v / 100)) . '</text>';
    }
    $i = 0;
    foreach ($months as $m => $v) {
        $x = $pl + $cw * $i + ($cw - $bw) / 2;
        $total = $v['new'] + $v['renewal'];
        $tip = month_label($m, true) . ' · ' . t('Baru', 'New') . ' ' . rm($v['new']) . ' · ' . t('Pembaharuan', 'Renewals') . ' ' . rm($v['renewal']) . ' · ' . t('Jumlah', 'Total') . ' ' . rm($total);
        $svg .= '<g class="col"><title>' . h($tip) . '</title><rect class="hit" x="' . round($pl + $cw * $i, 1) . '" y="' . $pt . '" width="' . round($cw, 1) . '" height="' . ($H - $pt - $pb) . '"/>';
        $segs = array_values(array_filter([['new', $v['new']], ['renewal', $v['renewal']]], fn($s) => $s[1] > 0));
        $base = $H - $pb;
        foreach ($segs as $k => $s) {
            $h = ($H - $pt - $pb) * $s[1] / $top;
            $gap = $k > 0 ? 2 : 0; // surface gap between stacked segments
            $hh = max(1, $h - $gap);
            $yy = $base - $h;
            $isTop = $k === count($segs) - 1;
            $cls = $s[0] === 'new' ? 'b-new' : 'b-renew';
            $r = $isTop ? min(4, $hh, $bw / 2) : 0;
            $svg .= '<path class="' . $cls . '" d="' . bar_path($x, $yy, $bw, $hh, $r) . '"/>';
            $base = $yy;
        }
        $svg .= '<text class="axis" x="' . round($x + $bw / 2, 1) . '" y="' . ($H - 10) . '" text-anchor="middle">' . month_label($m) . '</text></g>';
        $i++;
    }
    return $svg . '<line class="base" x1="' . $pl . '" x2="' . ($W - $pr) . '" y1="' . ($H - $pb) . '" y2="' . ($H - $pb) . '"/></svg>';
}

// A bar with only its top corners rounded, so it stays anchored to the baseline
function bar_path(float $x, float $y, float $w, float $h, float $r): string
{
    $f = fn($n) => round($n, 1);
    if ($r <= 0) {
        return 'M' . $f($x) . ' ' . $f($y + $h) . 'V' . $f($y) . 'H' . $f($x + $w) . 'V' . $f($y + $h) . 'Z';
    }
    return 'M' . $f($x) . ' ' . $f($y + $h) . 'V' . $f($y + $r) . 'Q' . $f($x) . ' ' . $f($y) . ' ' . $f($x + $r) . ' ' . $f($y)
        . 'H' . $f($x + $w - $r) . 'Q' . $f($x + $w) . ' ' . $f($y) . ' ' . $f($x + $w) . ' ' . $f($y + $r) . 'V' . $f($y + $h) . 'Z';
}
