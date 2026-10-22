<?php
// Set a password from the emailed link (new account, team invite or password reset).
declare(strict_types=1);
require __DIR__ . '/lib/layout.php';

$raw = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$tok = find_token($raw);
$err = '';
if ($tok && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    check_csrf();
    $p1 = (string)($_POST['password'] ?? '');
    $p2 = (string)($_POST['password2'] ?? '');
    if (strlen($p1) < 8) {
        $err = t('Kata laluan mesti sekurang-kurangnya 8 aksara.', 'The password needs at least 8 characters.');
    } elseif ($p1 !== $p2) {
        $err = t('Kedua-dua kata laluan tidak sama.', "The two passwords don't match.");
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        $done = q('UPDATE tokens SET used_at = ? WHERE id = ? AND used_at IS NULL', [now(), $tok['id']])->rowCount();
        if ($done) {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($p1, PASSWORD_DEFAULT), $tok['user_id']]);
            q('UPDATE tokens SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [now(), $tok['user_id']]);
        }
        $pdo->commit();
        if ($done) {
            $user = q('SELECT * FROM users WHERE id = ?', [$tok['user_id']])->fetch();
            log_in($user);
            header('Location: ' . ($user['role'] === 'superadmin' ? '/admin/' : '/app/?welcome=1'), true, 303);
            exit;
        }
        $tok = null;
    }
}

page_head(t('Tetapkan kata laluan', 'Set your password'));
echo '<div class="wrap narrow"><h1>' . t('Tetapkan kata laluan', 'Set your password') . '</h1>';
if (!$tok) {
    echo '<p class="lead" style="margin:16px 0">' . t('Pautan ini telah digunakan atau tamat tempoh.', 'This link has already been used or has expired.')
        . '</p><a class="btn primary" href="/app/forgot.php">' . t('Dapatkan pautan baru', 'Get a new link') . '</a>';
} else {
    echo '<p class="lead" style="margin:12px 0 24px">' . h($tok['email']) . '</p>';
    if ($err) {
        echo flash('bad', h($err));
    }
    echo '<form method="post" class="card stack">' . csrf_field() . '<input type="hidden" name="token" value="' . h($raw) . '">'
        . '<input type="email" name="username" value="' . h($tok['email']) . '" autocomplete="username" hidden>'
        . '<label class="field">' . t('Kata laluan baru (sekurang-kurangnya 8 aksara)', 'New password (at least 8 characters)')
        . '<input type="password" name="password" autocomplete="new-password" minlength="8" required autofocus></label>'
        . '<label class="field">' . t('Ulang kata laluan', 'Repeat the password') . '<input type="password" name="password2" autocomplete="new-password" minlength="8" required></label>'
        . '<button class="btn primary" type="submit">' . t('Simpan dan log masuk', 'Save and log in') . '</button></form>';
}
echo '</div>';
page_foot();
