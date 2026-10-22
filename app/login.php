<?php
// Log in for customers, their staff and the owner (who goes on to the dashboard).
declare(strict_types=1);
require __DIR__ . '/lib/layout.php';

$next = (string)($_GET['next'] ?? $_POST['next'] ?? '');
$u = current_user();
if ($u) {
    header('Location: ' . safe_next($next, $u['role'] === 'superadmin' ? '/admin/' : '/app/'), true, 303);
    exit;
}
$err = '';
$email = strtolower(trim((string)($_POST['email'] ?? '')));
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    check_csrf();
    $pw = (string)($_POST['password'] ?? '');
    if (too_many_attempts($email)) {
        $err = t('Terlalu banyak cubaan. Tunggu 15 minit dan cuba lagi.', 'Too many tries. Wait 15 minutes and try again.');
    } else {
        $row = q('SELECT * FROM users WHERE email = ? AND active = 1', [$email])->fetch();
        if ($row && $row['password_hash'] && password_verify($pw, $row['password_hash'])) {
            if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                $row['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
                q('UPDATE users SET password_hash = ? WHERE id = ?', [$row['password_hash'], $row['id']]);
            }
            log_in($row);
            header('Location: ' . safe_next($next, $row['role'] === 'superadmin' ? '/admin/' : '/app/'), true, 303);
            exit;
        }
        record_failed_login($email);
        $err = t('E-mel atau kata laluan salah.', 'Wrong email or password.');
    }
}

page_head(t('Log masuk', 'Log in'));
echo '<div class="wrap narrow"><h1>' . t('Log masuk', 'Log in') . '</h1><p class="lead" style="margin:12px 0 24px">'
    . t('Sistem HR, gaji dan kewangan syarikat anda.', "Your company's HR, payroll and finance system.") . '</p>';
if ($err) {
    echo flash('bad', h($err));
}
echo '<form method="post" class="card stack">' . csrf_field() . '<input type="hidden" name="next" value="' . h($next) . '">'
    . '<label class="field">' . t('E-mel', 'Email') . '<input type="email" name="email" value="' . h($email) . '" autocomplete="username" required autofocus></label>'
    . '<label class="field">' . t('Kata laluan', 'Password') . '<input type="password" name="password" autocomplete="current-password" required></label>'
    . '<button class="btn primary" type="submit">' . t('Log masuk', 'Log in') . '</button>'
    . '<a class="small-text" href="/app/forgot.php">' . t('Lupa kata laluan?', 'Forgot your password?') . '</a></form>'
    . '<p class="muted small-text" style="margin-top:20px">' . t('Belum melanggan?', 'Not subscribed yet?')
    . ' <a href="' . (lang() === 'en' ? '/en/subscribe/' : '/langgan/') . '">' . t('Langgan sekarang', 'Subscribe now') . '</a></p></div>';
page_foot();
