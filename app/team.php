<?php
// The company's logins: invite admins (full access) or staff (clock in only), resend invites, remove access.
declare(strict_types=1);
require __DIR__ . '/lib/layout.php';

$u = current_user();
if (!$u || !in_array($u['role'], ['owner', 'admin'], true) || !subscription_active($u) || $u['tenant_status'] !== 'active') {
    header('Location: /app/', true, 303);
    exit;
}
$tid = (int)$u['tenant_id'];
$msg = '';
$link = '';

function invite_mail(array $to, string $link, string $company, string $role): bool
{
    $what = $role === 'staff'
        ? t('daftar masuk dan keluar kerja', 'clock in and out of work')
        : t('menggunakan sistem HR, gaji dan kewangan', 'use the HR, payroll and finance system');
    return send_mail($to['email'], t("Jemputan ke sistem $company", "Your invitation to $company's system"), t(
        "Hai {$to['name']},\n\n$company menjemput anda untuk $what.\n\nTetapkan kata laluan anda di sini (pautan sah selama 7 hari):\n$link\n\nExcellent Synergy Resources",
        "Hi {$to['name']},\n\n$company has invited you to $what.\n\nSet your password here (the link works for 7 days):\n$link\n\nExcellent Synergy Resources"));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    check_csrf();
    $act = (string)($_POST['act'] ?? '');
    if ($act === 'invite') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'staff';
        $count = (int)q('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND active = 1', [$tid])->fetchColumn();
        if ($name === '' || mb_strlen($name) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = flash('bad', t('Isi nama dan e-mel yang sah.', 'Add a name and a valid email.'));
        } elseif ($count >= 300) {
            $msg = flash('bad', t('Had 300 pengguna dicapai. Hubungi kami.', 'You have reached 300 users. Contact us.'));
        } elseif (q('SELECT id FROM users WHERE email = ?', [$email])->fetch()) {
            $msg = flash('bad', t('E-mel ini sudah mempunyai akaun.', 'This email already has an account.'));
        } else {
            q('INSERT INTO users (tenant_id, email, name, role, created_at) VALUES (?, ?, ?, ?, ?)', [$tid, $email, $name, $role, now()]);
            $new = ['id' => (int)db()->lastInsertId(), 'email' => $email, 'name' => $name];
            $link = make_password_link($new['id']);
            $ok = invite_mail($new, $link, (string)$u['company'], $role);
            $msg = flash('good', h($name) . ' ' . ($ok ? t('telah dijemput melalui e-mel.', 'has been invited by email.')
                    : t('telah ditambah, tetapi e-mel gagal dihantar. Hantar pautan di bawah kepada mereka.', "was added, but the email couldn't be sent. Send them the link below.")));
        }
    } elseif ($act === 'resend' || $act === 'remove') {
        $t = q("SELECT * FROM users WHERE id = ? AND tenant_id = ? AND role <> 'owner'", [(int)($_POST['id'] ?? 0), $tid])->fetch();
        if ($t && $act === 'resend' && $t['active']) {
            $link = make_password_link((int)$t['id']);
            $ok = invite_mail($t, $link, (string)$u['company'], $t['role']);
            $msg = flash($ok ? 'good' : 'bad', $ok ? t('Jemputan dihantar semula.', 'Invitation sent again.') : t('E-mel gagal dihantar. Hantar pautan di bawah kepada mereka.', "The email couldn't be sent. Send them the link below."));
        } elseif ($t && $act === 'remove' && (int)$t['id'] !== (int)$u['id']) {
            q('UPDATE users SET active = 0 WHERE id = ?', [$t['id']]);
            q('UPDATE tokens SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [now(), $t['id']]);
            $msg = flash('good', h($t['name']) . ' ' . t('tidak lagi boleh log masuk.', 'can no longer log in.'));
        }
    } elseif ($act === 'restore') {
        $t = q("SELECT * FROM users WHERE id = ? AND tenant_id = ? AND role <> 'owner'", [(int)($_POST['id'] ?? 0), $tid])->fetch();
        if ($t) {
            q('UPDATE users SET active = 1 WHERE id = ?', [$t['id']]);
            $msg = flash('good', h($t['name']) . ' ' . t('boleh log masuk semula.', 'can log in again.'));
        }
    }
}

$users = q('SELECT * FROM users WHERE tenant_id = ? ORDER BY active DESC, role = \'owner\' DESC, name', [$tid])->fetchAll();
$roles = ['owner' => t('Pemilik', 'Owner'), 'admin' => t('Pentadbir', 'Admin'), 'staff' => t('Kakitangan', 'Staff')];

page_head(t('Pasukan', 'Team'), $u);
echo '<div class="wrap"><p><a href="/app/">← ' . t('Akaun saya', 'My account') . '</a></p><h1 style="margin-top:10px">' . t('Pasukan', 'Team') . '</h1>'
    . '<p class="lead" style="margin:12px 0 24px">' . t('<b>Pentadbir</b> boleh menggunakan HR dan Kewangan sepenuhnya. <b>Kakitangan</b> hanya boleh daftar masuk dan keluar kerja.',
        '<b>Admins</b> can use HR and Finance fully. <b>Staff</b> can only clock in and out of work.') . '</p>' . $msg;
if ($link) {
    echo '<div class="card" style="margin-bottom:20px"><p class="small-text muted" style="margin-bottom:8px">' . t('Pautan jemputan (sah 7 hari):', 'Invitation link (works for 7 days):')
        . '</p><div class="copy"><input readonly value="' . h($link) . '" onclick="this.select()"><button class="btn small" type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value);this.textContent=\''
        . t('Disalin', 'Copied') . '\'">' . t('Salin', 'Copy') . '</button></div></div>';
}
echo '<div class="grid2"><form method="post" class="card stack">' . csrf_field() . '<input type="hidden" name="act" value="invite"><h2>' . t('Jemput', 'Invite') . '</h2>'
    . '<label class="field">' . t('Nama', 'Name') . '<input name="name" maxlength="160" required></label>'
    . '<label class="field">' . t('E-mel', 'Email') . '<input type="email" name="email" maxlength="190" required></label>'
    . '<label class="field">' . t('Akses', 'Access') . '<select name="role"><option value="staff">' . t('Kakitangan: daftar masuk sahaja', 'Staff: clock in only') . '</option><option value="admin">'
    . t('Pentadbir: akses penuh', 'Admin: full access') . '</option></select></label>'
    . '<button class="btn primary" type="submit">' . t('Hantar jemputan', 'Send invitation') . '</button></form>'
    . '<div class="card"><h2>' . t('Petua', 'Tip') . '</h2><p class="muted">' . t('Gunakan e-mel yang sama seperti dalam rekod pekerja di aplikasi HR, supaya daftar masuk dipadankan dengan pekerja yang betul.',
        "Use the same email as in the employee's record in the HR app, so their clock-ins match the right person.") . '</p></div></div>';
echo '<h2 style="margin-top:34px">' . t('Pengguna', 'Users') . ' (' . count(array_filter($users, fn($x) => $x['active'])) . ')</h2><div class="tbl-wrap"><table class="tbl"><thead><tr><th>'
    . t('Nama', 'Name') . '</th><th>' . t('E-mel', 'Email') . '</th><th>' . t('Akses', 'Access') . '</th><th>' . t('Log masuk terakhir', 'Last log in') . '</th><th></th></tr></thead><tbody>';
foreach ($users as $x) {
    $actions = '';
    if ($x['role'] !== 'owner' && (int)$x['id'] !== (int)$u['id']) {
        $f = fn($act, $label) => '<form method="post">' . csrf_field() . '<input type="hidden" name="act" value="' . $act . '"><input type="hidden" name="id" value="' . (int)$x['id']
            . '"><button class="linkbtn" type="submit">' . $label . '</button></form>';
        $actions = $x['active'] ? (!$x['password_hash'] ? $f('resend', t('Hantar semula', 'Resend')) . ' · ' : '') . $f('remove', t('Buang akses', 'Remove access'))
            : $f('restore', t('Pulihkan', 'Restore'));
    }
    echo '<tr' . ($x['active'] ? '' : ' class="muted"') . '><td>' . h($x['name']) . '</td><td>' . h($x['email']) . '</td><td>' . $roles[$x['role']]
        . (!$x['active'] ? ' · ' . t('dibuang', 'removed') : (!$x['password_hash'] ? ' · ' . t('belum log masuk', 'not set up yet') : ''))
        . '</td><td>' . ($x['last_login_at'] ? fmt_date($x['last_login_at']) : '-') . '</td><td>' . $actions . '</td></tr>';
}
echo '</tbody></table></div></div>';
page_foot();
